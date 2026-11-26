"""Audit locked dependencies and maintain assigned GitHub alert issues."""

import argparse
import hashlib
import html
import json
import os
from pathlib import Path
import subprocess
import urllib.parse
import urllib.request


MARKER = "<!-- stem-dependency-audit -->"
TEST_MARKER = "<!-- stem-dependency-audit-notification-test -->"
URGENT = {"high", "critical", "unknown"}


def severity(value):
    value = str(value or "unknown").lower()
    value = "moderate" if value == "medium" else value
    return value if value in {"info", "low", "moderate", "high", "critical"} else "unknown"


def composer_findings(data, lock):
    if not isinstance(data, dict) or data.get("error") or "advisories" not in data:
        raise ValueError("Composer did not return an advisory report")
    advisories = data["advisories"]
    if not isinstance(advisories, dict) and advisories != []:
        raise ValueError("Unsupported Composer advisory format")
    development = {package["name"] for package in lock.get("packages-dev", [])}
    findings = []
    for package, entries in dict(advisories).items():
        # Composer may encode a sparse advisory array as a JSON object.
        for entry in entries.values() if isinstance(entries, dict) else entries:
            findings.append({
                "ecosystem": "Composer", "package": package,
                "scope": "development" if package in development else "production",
                "severity": severity(entry.get("severity")),
                "title": entry["title"], "url": entry.get("link", ""),
                "fix": "Review the advisory for patched versions",
            })
    abandoned = data.get("abandoned", {})
    if not isinstance(abandoned, dict) and abandoned != []:
        raise ValueError("Unsupported Composer abandoned-package format")
    return findings, dict(abandoned)


def npm_findings(data, production_names):
    if (not isinstance(data, dict) or data.get("error")
            or data.get("auditReportVersion") != 2
            or not isinstance(data.get("vulnerabilities"), dict)):
        raise ValueError("npm did not return a version 2 vulnerability report")
    findings = []
    for package, entry in data["vulnerabilities"].items():
        advisories = [via for via in entry.get("via", []) if isinstance(via, dict)]
        fix = entry.get("fixAvailable", False)
        if isinstance(fix, dict):
            fix_text = f"Update {fix['name']} to {fix['version']}"
            if fix.get("isSemVerMajor"):
                fix_text += " (major version; review compatibility)"
        else:
            fix_text = "Compatible update available" if fix else "No automatic fix; review advisory"
        findings.append({
            "ecosystem": "npm", "package": package,
            "scope": "production" if package in production_names else "development",
            "severity": severity(entry.get("severity")),
            "title": "; ".join(via["title"] for via in advisories) or "Affected through a dependency",
            "url": advisories[0].get("url", "") if advisories else "",
            "fix": fix_text,
        })
    return findings


def urgent_findings(report):
    return [finding for finding in report["findings"] if finding["severity"] in URGENT]


def blocking(report):
    # Preserve the PR policy: all Composer advisories/abandonments and high or
    # critical production npm findings block. Development npm findings still alert.
    return bool(report["errors"] or report["abandoned"] or any(
        finding["ecosystem"] == "Composer"
        or (finding["scope"] == "production" and finding["severity"] in URGENT)
        for finding in report["findings"]
    ))


def cell(value):
    return html.escape(str(value)).replace("|", "\\|").replace("\n", " ").replace("@", "&#64;")


def markdown(report):
    lines = ["## Dependency audit", "", f"[Workflow run]({report['run_url']})", ""]
    if report["errors"]:
        lines += ["**The audit is incomplete. A failed scan does not mean dependencies are safe.**", ""]
        lines += [f"- {cell(error)}" for error in report["errors"]]
        lines.append("")
    lines += [f"{len(urgent_findings(report))} high, critical or unclassified findings; "
              f"{len(report['findings'])} total affected packages/advisories.", ""]
    if report["findings"]:
        lines += ["| Ecosystem | Package | Scope | Severity | Finding | Update guidance |",
                  "| --- | --- | --- | --- | --- | --- |"]
        for finding in sorted(report["findings"], key=lambda f: (f["ecosystem"], f["package"], f["title"])):
            title = cell(finding["title"])
            url = finding["url"]
            if urllib.parse.urlsplit(url).scheme in {"http", "https"}:
                safe_url = urllib.parse.quote(url, safe=":/?=&%#-._~")
                title += f" ([advisory]({safe_url}))"
            lines.append("| " + " | ".join([
                cell(finding["ecosystem"]), cell(finding["package"]), cell(finding["scope"]),
                cell(finding["severity"]), title, cell(finding["fix"]),
            ]) + " |")
        lines.append("")
    if report["abandoned"]:
        lines += ["### Abandoned Composer packages", ""]
        lines += [f"- {cell(package)}: {cell(replacement or 'no replacement suggested')}"
                  for package, replacement in report["abandoned"].items()]
        lines.append("")
    lines += ["High/critical findings in production or development dependencies need prompt review. "
              "Unclassified advisories need manual severity review. Build-tool findings are reported "
              "without changing the production npm PR gate.", "",
              "Review advisory impact and patched versions, update on a branch, and run the project checks. "
              "Raw audit JSON and errors are attached to the workflow run."]
    return "\n".join(lines)


def run_url():
    return (f"{os.environ.get('GITHUB_SERVER_URL', 'https://github.com')}/"
            f"{os.environ.get('GITHUB_REPOSITORY', 'STEMMechanics/website')}/actions/runs/"
            f"{os.environ.get('GITHUB_RUN_ID', '')}")


def collect(directory):
    directory.mkdir(parents=True, exist_ok=True)
    report = {"findings": [], "abandoned": {}, "errors": [], "run_url": run_url()}
    commands = {
        "composer": ["composer", "--no-cache", "--no-plugins", "--no-scripts", "audit", "--locked", "--format=json"],
        "npm-all": ["npm", "audit", "--package-lock-only", "--include=dev", "--json"],
        "npm-production": ["npm", "audit", "--package-lock-only", "--omit=dev", "--json"],
    }
    results = {}
    for name, command in commands.items():
        try:
            result = subprocess.run(command, capture_output=True, text=True, timeout=120, check=False)
            (directory / f"{name}.json").write_text(result.stdout)
            (directory / f"{name}.stderr.txt").write_text(result.stderr)
            data = json.loads(result.stdout)
            if name == "composer":
                findings, abandoned = composer_findings(data, json.loads(Path("composer.lock").read_text()))
                report["findings"].extend(findings)
                report["abandoned"] = abandoned
                has_findings = bool(findings or abandoned)
            else:
                # Validate both npm responses before using the production scope.
                has_findings = bool(npm_findings(data, set()))
            if result.returncode not in {0, 1, 2, 3} or (result.returncode and not has_findings):
                raise ValueError(f"command failed with exit code {result.returncode}")
            results[name] = data
        except (OSError, subprocess.TimeoutExpired, ValueError, KeyError, TypeError) as error:
            report["errors"].append(f"{name}: {error}")
    if "npm-all" in results:
        production = set(results.get("npm-production", {}).get("vulnerabilities", {}))
        report["findings"].extend(npm_findings(results["npm-all"], production))
    (directory / "report.json").write_text(json.dumps(report, indent=2) + "\n")
    summary = markdown(report)
    (directory / "report.md").write_text(summary + "\n")
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write(summary + "\n")
    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a") as output:
            output.write("report=" + json.dumps(report, separators=(",", ":")) + "\n")
    print(summary)
    return report


class GitHub:
    def __init__(self):
        self.base = os.environ.get("GITHUB_API_URL", "https://api.github.com")
        self.repository = os.environ["GITHUB_REPOSITORY"]

    def request(self, method, path, payload=None):
        request = urllib.request.Request(
            f"{self.base}/repos/{self.repository}/{path}", method=method,
            data=json.dumps(payload).encode() if payload is not None else None,
            headers={"Authorization": f"Bearer {os.environ['GH_TOKEN']}",
                     "Accept": "application/vnd.github+json", "Content-Type": "application/json",
                     "X-GitHub-Api-Version": "2022-11-28"},
        )
        with urllib.request.urlopen(request, timeout=30) as response:
            return json.load(response)

    def issues(self):
        page = 1
        while True:
            issues = self.request("GET", f"issues?state=all&per_page=100&page={page}")
            yield from issues
            if len(issues) < 100:
                return
            page += 1


def sync_issue(api, report, assignee, test=False):
    marker = TEST_MARKER if test else MARKER
    existing = next((issue for issue in api.issues()
                     if not issue.get("pull_request")
                     and issue.get("user", {}).get("login") == "github-actions[bot]"
                     and (issue.get("body") or "").startswith(marker)), None)
    attention = bool(urgent_findings(report) or blocking(report) or test)
    if not existing and not attention:
        return None
    key = hashlib.sha256(json.dumps({
        "findings": sorted(report["findings"], key=lambda f: json.dumps(f, sort_keys=True)),
        "abandoned": report["abandoned"],
        "errors": report["errors"],
    }, sort_keys=True).encode()).hexdigest()
    fingerprint = f"<!-- audit-fingerprint: {key} -->"
    title = ("[Test] Dependency alert notification" if test else
             "Dependency audit could not complete" if report["errors"] else
             "Urgent dependency updates required" if urgent_findings(report) else
             "Dependency updates need review" if attention else
             "Dependency audit resolved")
    body = marker + "\n" + fingerprint + "\n\n"
    if test:
        body += ("This is a notification delivery test, **not a vulnerability alert**. "
                 "Check your GitHub inbox and configured email inbox for this assigned issue. "
                 "Close it once delivery is confirmed.\n\n")
    body += markdown(report)
    # GitHub limits issue bodies to 65,536 characters; raw reports retain full details.
    body = body[:60000]
    payload = {"title": title, "body": body, "assignees": [assignee]}
    if existing:
        payload["state"] = "open" if attention else "closed"
        issue = api.request("PATCH", f"issues/{existing['number']}", payload)
        if attention and (test or fingerprint not in (existing.get("body") or "")):
            api.request("POST", f"issues/{existing['number']}/comments", {
                "body": ("Notification delivery test requested." if test else
                         "Dependency findings or audit errors have changed.")
                        + f"\n\n[Review the audit run]({report['run_url']}).",
            })
    else:
        issue = api.request("POST", "issues", payload)
    print(issue["html_url"])
    return issue["html_url"]


def notify():
    serialized = os.environ.get("AUDIT_REPORT", "")
    try:
        report = json.loads(serialized)
        if (not isinstance(report, dict) or not isinstance(report.get("findings"), list)
                or not isinstance(report.get("errors"), list)
                or not isinstance(report.get("abandoned"), dict) or not report.get("run_url")):
            raise ValueError("Invalid audit report")
    except (ValueError, TypeError):
        report = {"findings": [], "abandoned": {},
                  "errors": ["Audit job did not produce a valid report"], "run_url": run_url()}
    if os.environ.get("AUDIT_VALIDATION_RESULT") != "success":
        report["errors"].append("Composer validation did not succeed; inspect the workflow run")
    if os.environ.get("AUDIT_JOB_RESULT") != "success" and not blocking(report):
        report["errors"].append("Audit job failed; inspect validation, setup and scan results")
    api = GitHub()
    assignee = os.environ["AUDIT_ASSIGNEE"]
    # A fixed dependency on an unmerged branch must not resolve the main alert.
    default_ref = "refs/heads/" + os.environ["AUDIT_DEFAULT_BRANCH"]
    urls = [sync_issue(api, report, assignee)] if os.environ.get("GITHUB_REF") == default_ref else []
    if os.environ.get("NOTIFICATION_TEST") == "true":
        urls.append(sync_issue(api, report, assignee, test=True))
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write("\n".join(f"- [Dependency notification issue]({url})" for url in urls if url) + "\n")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=["collect", "notify"])
    parser.add_argument("--output-dir", type=Path, default=Path("audit-reports"))
    args = parser.parse_args()
    if args.command == "collect":
        raise SystemExit(1 if blocking(collect(args.output_dir)) else 0)
    notify()
