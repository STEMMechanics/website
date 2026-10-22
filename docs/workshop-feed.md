# Workshop RSS feed

The public workshop feed is available at `GET /workshops/feed` and returns
RSS 2.0 with `Content-Type: application/rss+xml; charset=UTF-8`.

The feed keeps its existing RSS elements and the existing `sm` namespace:
`https://stemmechanics.com/ns/rss/workshop`. RSS readers that do not know the
namespace ignore the additional `sm:*` elements and continue to use the
standard fields.

## Published fields

The channel contains these fields:

| XML field | Occurrence | Meaning |
| --- | --- | --- |
| `channel/title` | Always | `STEMMechanics Workshops` |
| `channel/link` | Always | Public workshop listing URL |
| `channel/description` | Always | Feed description |
| `channel/language` | Always | `en-au` |
| `channel/lastBuildDate` | Always | Feed generation time in RSS date format |
| `channel/atom:link` | Always | Self link for the feed |

Each published `<item>` contains the standard RSS fields below. The
`enclosure`, `sm:startDate` and `sm:endDate` fields remain conditional exactly
as they were before these enhancements.

| XML field | Occurrence | Meaning |
| --- | --- | --- |
| `item/title` | Always | Workshop title |
| `item/link` | Always | Public workshop page |
| `item/guid` | Always | Existing permalink GUID, preserved for compatibility |
| `item/pubDate` | Always | Workshop update time, or the existing publish/create fallback |
| `item/description` | Always | Existing summary and schedule/location text |
| `item/enclosure` | When a public hero image is available | Existing image enclosure with URL and MIME type |
| `item/sm:startDate` | When a start date exists | Effective workshop start in ISO 8601 format with offset |
| `item/sm:endDate` | When an end date exists | Effective workshop end in ISO 8601 format with offset |
| `item/sm:id` | Always | Immutable workshop database ID; use this as STEMBot's item key |
| `item/sm:location` | Always | Existing public location label |
| `item/sm:price` | When a public price is available | Existing formatted price |
| `item/sm:ages` | Always | Age guidance, or `All ages` |
| `item/sm:registrationType` | Always | Lowercase registration workflow enum |
| `item/sm:registrationUrl` | When an actionable public URL is available | URL for the workflow described by `sm:registrationType` |
| `item/sm:status` | Always | Existing human-readable status label |
| `item/sm:statusCode` | Always | Lowercase machine-readable status code |
| `item/sm:type` | Always | Workshop type enum: `physical`, `online` or `stemcraft` |

The existing `guid` and `link` values are unchanged. Because the current URL
contains a title slug, title edits can change those values; STEMBot should use
`sm:id` when matching an item across feed polls.

## Registration workflows

`sm:registrationType` is one of `none`, `tickets`, `link`, `interest`,
`email` or `message`.

| `sm:registrationType` | `sm:registrationUrl` |
| --- | --- |
| `none` | Omitted |
| `tickets` | STEMMechanics ticket-flow start URL |
| `link` | The validated public HTTP(S) registration URL; invalid or missing link data falls back to the workshop page |
| `interest` | Workshop page, where the interest workflow is presented |
| `email` | A `mailto:` URL when the stored address is valid; otherwise the workshop page |
| `message` | Workshop page, where the organiser's message is presented |

The URL is omitted for private, cancelled, or otherwise non-open items. A
private item can still publish its `registrationType`, but the feed does not
leak a private external registration target or email address.

The message workflow does not place message text in a URL field. STEMBot can
follow `item/link` or `sm:registrationUrl` to read the instructions from the
workshop page.

## Status and visibility rules

The existing `sm:status` remains unchanged for current RSS consumers.
`sm:statusCode` is the machine-readable value and is the only cancellation
signal:

- A cancelled workshop is published for 30 days after its `updated_at` time,
  even if its scheduled date has passed. It has `sm:statusCode` set to
  `cancelled`, keeps its existing `sm:status` value of `Cancelled`, and has no
  `sm:registrationUrl`.
- A hidden workshop is omitted from the public feed, including when it was
  previously published. Hidden data is not emitted as a tombstone.
- A deleted workshop is omitted after its database record is deleted. The
  current deletion workflow does not retain a feed tombstone.
- Draft workshops and workshops whose future publish date has not arrived are
  omitted.
- Private workshops remain represented as they were historically, with safe
  public location text and `sm:statusCode` set to `private` when their stored
  status is `open`.

STEMBot should not infer cancellation from an item disappearing: hidden and
deleted items are also absent. Match previously seen items by `sm:id` and use
`sm:statusCode=cancelled` for cancellation notifications.

## Multi-session course proposal

The current feed publishes a course's effective first session as
`sm:startDate` and effective last session as `sm:endDate`. This remains the
compatibility envelope for existing consumers.

For a future enhancement, publish individual course sessions as a namespaced
child collection while retaining those top-level fields:

```xml
<sm:sessions>
    <sm:session>
        <sm:id>session-uuid</sm:id>
        <sm:position>1</sm:position>
        <sm:startDate>2026-11-05T09:00:00+10:00</sm:startDate>
        <sm:endDate>2026-11-05T11:00:00+10:00</sm:endDate>
    </sm:session>
    <sm:session>
        <sm:id>another-session-uuid</sm:id>
        <sm:position>2</sm:position>
        <sm:startDate>2026-11-12T09:00:00+10:00</sm:startDate>
        <sm:endDate>2026-11-12T11:00:00+10:00</sm:endDate>
    </sm:session>
</sm:sessions>
```

The session `id` should use the existing persisted session UUID, and
`position` should preserve the course order. This gives STEMBot exact dates
without asking ordinary RSS readers to understand a new top-level item shape.
The session collection is proposed only; it is not published by the current
feed.
