import copy
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch


spec = importlib.util.spec_from_file_location("dependency_audit", Path(__file__).with_name("dependency_audit.py"))
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


def report(findings=None, errors=None):
    return {"findings": findings or [], "errors": errors or [], "abandoned": {},
            "run_url": "https://github.com/example/repo/actions/runs/123"}


def finding(level="high", scope="production", ecosystem="npm"):
    return {"ecosystem": ecosystem, "package": "example", "severity": level, "scope": scope,
            "title": "Example advisory", "url": "https://example.com/advisory", "fix": "Update"}


def npm_data(vulnerabilities=None):
    return {"auditReportVersion": 2, "vulnerabilities": vulnerabilities or {}}


class FakeGitHub:
    def __init__(self):
        self.stored = []
        self.requests = []

    def issues(self):
        return iter(copy.deepcopy(self.stored))

    def request(self, method, path, payload):
        self.requests.append((method, path, copy.deepcopy(payload)))
        if path == "issues":
            issue = {**payload, "number": len(self.stored) + 1, "state": "open",
                     "user": {"login": "github-actions[bot]"}, "html_url": "https://example.com/issue"}
            self.stored.append(issue)
        elif method == "PATCH":
            issue = next(item for item in self.stored if item["number"] == int(path.split("/")[1]))
            issue.update(payload)
        else:
            return {}
        return copy.deepcopy(issue)


class DependencyAuditTests(unittest.TestCase):
    def test_development_npm_is_urgent_without_changing_production_gate(self):
        data = report([finding(scope="development")])
        self.assertTrue(audit.urgent_findings(data))
        self.assertFalse(audit.blocking(data))
        self.assertTrue(audit.blocking(report([finding()])))
        self.assertFalse(audit.blocking(report([finding("moderate")])))
        self.assertTrue(audit.blocking(report([finding("low", ecosystem="Composer")])))

    def test_composer_sparse_arrays_severity_and_development_scope(self):
        findings, abandoned = audit.composer_findings({
            "advisories": {"example": {"2": {"title": "First", "severity": "medium"},
                                         "4": {"title": "Second"}}},
            "abandoned": {"old-package": None},
        }, {"packages-dev": [{"name": "example"}]})
        self.assertEqual([item["severity"] for item in findings], ["moderate", "unknown"])
        self.assertEqual(findings[0]["scope"], "development")
        self.assertEqual(abandoned, {"old-package": None})
        self.assertTrue(audit.urgent_findings(report(findings)))
        self.assertEqual(audit.severity("unrecognized"), "unknown")

    def test_invalid_reports_are_errors_instead_of_clean_audits(self):
        for data in ({}, [], {"error": {"code": "NETWORK"}}, {"advisories": "broken"}):
            with self.subTest(data=data), self.assertRaises(ValueError):
                audit.composer_findings(data, {})
        for data in ({}, [], {"error": {}}, {"auditReportVersion": 1, "vulnerabilities": {}}):
            with self.subTest(data=data), self.assertRaises(ValueError):
                audit.npm_findings(data, set())

    def test_npm_scope_major_fix_and_indirect_findings(self):
        findings = audit.npm_findings(npm_data({
            "runtime": {"severity": "critical", "via": [{"title": "Advisory", "url": "https://example.com"}],
                        "fixAvailable": {"name": "parent", "version": "2.0.0", "isSemVerMajor": True}},
            "tool": {"severity": "high", "via": ["runtime"], "fixAvailable": False},
        }), {"runtime"})
        self.assertEqual(findings[0]["scope"], "production")
        self.assertIn("major version", findings[0]["fix"])
        self.assertEqual(findings[1]["scope"], "development")
        self.assertEqual(findings[1]["title"], "Affected through a dependency")

    def test_all_scans_run_when_one_fails_and_raw_reports_survive(self):
        results = [subprocess.CompletedProcess([], 1, "not json", "network failed"),
                   subprocess.CompletedProcess([], 0, json.dumps(npm_data()), ""),
                   subprocess.CompletedProcess([], 0, json.dumps(npm_data()), "")]
        with tempfile.TemporaryDirectory() as directory, patch.object(audit.subprocess, "run", side_effect=results) as run:
            with patch.dict(os.environ, {}, clear=True), patch("builtins.print"):
                data = audit.collect(Path(directory))
            self.assertEqual(run.call_count, 3)
            self.assertIn("--package-lock-only", run.call_args_list[1].args[0])
            self.assertTrue(audit.blocking(data))
            self.assertIn("composer:", data["errors"][0])
            self.assertEqual(Path(directory, "composer.stderr.txt").read_text(), "network failed")
            self.assertTrue(Path(directory, "report.json").exists())

    def test_nonzero_empty_audit_is_not_treated_as_success(self):
        results = [subprocess.CompletedProcess([], 0, '{"advisories": [], "abandoned": []}', ""),
                   subprocess.CompletedProcess([], 1, json.dumps(npm_data()), ""),
                   subprocess.CompletedProcess([], 0, json.dumps(npm_data()), "")]
        with tempfile.TemporaryDirectory() as directory, patch.object(audit.subprocess, "run", side_effect=results):
            with patch.dict(os.environ, {}, clear=True), patch("builtins.print"):
                data = audit.collect(Path(directory))
            self.assertTrue(any("npm-all:" in error for error in data["errors"]))

    def test_issue_deduplication_escalation_and_resolution(self):
        api = FakeGitHub()
        data = report([finding()])
        audit.sync_issue(api, data, "owner")
        audit.sync_issue(api, data, "owner")
        self.assertEqual(len(api.stored), 1)
        self.assertEqual(api.stored[0]["assignees"], ["owner"])
        self.assertFalse(any(path.endswith("comments") for _, path, _ in api.requests))
        audit.sync_issue(api, report([finding("critical")]), "owner")
        self.assertEqual(sum(path.endswith("comments") for _, path, _ in api.requests), 1)
        audit.sync_issue(api, report(), "owner")
        self.assertEqual(api.stored[0]["state"], "closed")
        audit.sync_issue(api, data, "owner")
        self.assertEqual(len(api.stored), 1)
        self.assertEqual(api.stored[0]["state"], "open")

    def test_scan_error_keeps_alert_open_without_claiming_clean_dependencies(self):
        api = FakeGitHub()
        audit.sync_issue(api, report([finding()]), "owner")
        audit.sync_issue(api, report(errors=["npm registry unavailable"]), "owner")
        self.assertEqual(api.stored[0]["state"], "open")
        self.assertIn("could not complete", api.stored[0]["title"])
        self.assertIn("audit is incomplete", api.stored[0]["body"])

    def test_moderate_composer_and_abandoned_packages_get_review_issues(self):
        for data in (report([finding("moderate", ecosystem="Composer")]),
                     {**report(), "abandoned": {"old": None}}):
            api = FakeGitHub()
            audit.sync_issue(api, data, "owner")
            self.assertEqual(api.stored[0]["title"], "Dependency updates need review")

    def test_notification_test_is_separate_from_real_alert(self):
        api = FakeGitHub()
        audit.sync_issue(api, report([finding()]), "owner")
        audit.sync_issue(api, report(), "owner", test=True)
        audit.sync_issue(api, report(), "owner", test=True)
        self.assertEqual(len(api.stored), 2)
        self.assertTrue(api.stored[0]["body"].startswith(audit.MARKER))
        self.assertTrue(api.stored[1]["body"].startswith(audit.TEST_MARKER))
        self.assertIn("not a vulnerability alert", api.stored[1]["body"])
        self.assertEqual(api.stored[0]["state"], "open")

    def test_clean_scan_does_not_create_issue_and_external_text_is_escaped(self):
        api = FakeGitHub()
        self.assertIsNone(audit.sync_issue(api, report(), "owner"))
        data = report([{**finding(), "title": "unsafe | table\n@someone <html>"}])
        text = audit.markdown(data)
        self.assertNotIn("@someone", text)
        self.assertIn("&#64;someone", text)
        self.assertIn("\\| table", text)
        self.assertNotIn("<html>", text)

    def test_branch_notification_test_cannot_resolve_main_alert(self):
        api = FakeGitHub()
        audit.sync_issue(api, report([finding()]), "owner")
        env = {"AUDIT_REPORT": json.dumps(report()), "AUDIT_JOB_RESULT": "success",
               "AUDIT_VALIDATION_RESULT": "success", "AUDIT_ASSIGNEE": "owner",
               "AUDIT_DEFAULT_BRANCH": "main", "GITHUB_REF": "refs/heads/a-fix",
               "NOTIFICATION_TEST": "true"}
        with patch.dict(os.environ, env, clear=True), patch.object(audit, "GitHub", return_value=api):
            audit.notify()
        self.assertEqual(api.stored[0]["state"], "open")
        self.assertEqual(len(api.stored), 2)
        self.assertIn("[Test]", api.stored[1]["title"])

    def test_missing_report_raises_an_assigned_scan_failure_issue(self):
        api = FakeGitHub()
        env = {"AUDIT_REPORT": "broken JSON", "AUDIT_JOB_RESULT": "failure",
               "AUDIT_VALIDATION_RESULT": "skipped", "AUDIT_ASSIGNEE": "owner",
               "AUDIT_DEFAULT_BRANCH": "main", "GITHUB_REF": "refs/heads/main"}
        with patch.dict(os.environ, env, clear=True), patch.object(audit, "GitHub", return_value=api):
            audit.notify()
        self.assertIn("could not complete", api.stored[0]["title"])
        self.assertIn("valid report", api.stored[0]["body"])

    def test_vulnerability_gate_failure_is_not_misreported_as_failed_scanning(self):
        api = FakeGitHub()
        env = {"AUDIT_REPORT": json.dumps(report([finding()])), "AUDIT_JOB_RESULT": "failure",
               "AUDIT_VALIDATION_RESULT": "success", "AUDIT_ASSIGNEE": "owner",
               "AUDIT_DEFAULT_BRANCH": "main", "GITHUB_REF": "refs/heads/main"}
        with patch.dict(os.environ, env, clear=True), patch.object(audit, "GitHub", return_value=api):
            audit.notify()
        self.assertEqual(api.stored[0]["title"], "Urgent dependency updates required")


if __name__ == "__main__":
    unittest.main()
