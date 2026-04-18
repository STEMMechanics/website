# Changes

## 1.4.4

- Improved UI
- Added x-day account support
- Improved email template on mobile
- Scheduled email status at times would be incorrect
- Support 3 decimal places in product sizing
- Allow dynamic backorder dates
- Product SKU now prefilled
- No expense attachment notice fix
- Added product subtitles
- Improved site search
- Expanded product categories
- Dependency updates
- Added editable workshop pick lists
- Ability to replaced manual EFTPOS replacements with late Square payments
- Improved invoice cancellations
- Bug fixes
- Improved file snapshots
- Ability to locate unused media
- Ability to view and restore from file backups
- Handle duplicate square events gracefully
- Voucher support for tickets
- Voucher editor improvements
- Added additional voucher restrictions
- Added groups claim for OpenID
- Minecraft player info now shows previous names
- Show warnings on the STEMCraft punishments page

## 1.4.3

- Added OpenID/OAuth2 support
- Dependency updates
- User account and leaderboard cleanup
- Added classroom support
- Change GITEA_BASE_URL to trusted providers
- Changed admin /webhooks to /webhook-logs for clarity
- Added admin button to for artisan clear and queue restart
- Collapsable section and tip tap fixes
- Added site option to hide child accounts
- Fixed blade parsing error
- My Tickets page now lists outstanding payments
- Improved UI
- Image selector retains previous value on form validation error
- Email receipt option for bank transfers
- Security and auditing
- Integrated deploy script into repo
- Fix for Square idempotency_key limit
- Updated email templates

## 1.4.2

- Improved display of invoice data in the admin pages
- TAN will now invoice sync to update invoice statuses

## 1.4.1

- Update times less than 60 seconds are now indicated as 'just now'
- Updated the STEMCraft pages

## 1.4.0

- Fixed minor issues around workshop SEO
- Added support for tables and better media handling in editor
- Improved UI on mobile devices
- Added forum support
- Added direct camera support for media manager
- Added support for usernames
- Includes descriptions for payments/expenses in BAS reports
- Media manager shows icon for private images (with hover)
- Updated copy across the website
- Square Webhooks now shows amounts in mobile views
- STEMCraft management is now available
- STEMCraft pages updated
- Updated dependencies
- Improved attendance and payment flows
- Highlighted not to use your real name as your username
- STEMMechanics accounts now highlighted in discussions
- Add group to workshop registrations
- Hide registration button on non-open workshops
- Implemented bot protection on forms
- Newsletter now excludes private and hidden workshops
- Dependency updates
- STEMMechanics users now prefixed with toolbox in discussions
- Discussion titles now support basic markdown
- Added ability to send a subscription email to a manual address
- Added List-Unsubscribe header to subscription emails
- Quote/Invoice Due date now moves to next business day
- Allow quotes to link to multiple invoices
- Added child accounts
- Added ability to create/reserve tickets under admin
- Fix Cannot drop index 'invoices_quote_id_unique' migration error
- Improvements in login flow for child accounts
- Improvements in manually creating tickets

## 1.3.14

- Updated MySQL SSL CA PDO constant usage for PHP 8.5 compatibility (`Pdo\Mysql::ATTR_SSL_CA` fallback handling)
- Improved handling of variant generation
- UI Improvements
- Fixed bug in passing external registration URLs for workshops
- Improved BAS reporting
- Dependency updates
- Private access codes are now case insensitive
- Improved site options across the project
- Improved refund wording
- Improved error handling of Altcha
- Ticket hold minutes is now a site option
- Show Site option descriptions
- Cleanup and standardization of site options
- Pagination of Square Webhooks page

## 1.3.13

- Added ability to enter decimal numbers into quantities of quotes and invoices

## 1.3.12

- Square Webhook management improvements
- UI Improvements

## 1.3.11

- Separated file upload to its own component
- File upload now supports drag and drop
- Orphan file downloads are now streamed
- Added ability to delete orphaned files
- UI Improvements
- Removed login notification on session refresh of remembered devices
- Improved file management on quotes/invoices
- Quotes now support a PO field
- Guarding around deleting users
- Square EFTPOS payments are now recorded automatically
- BAS Report improvements
- Square Webhook EFTPOS payment improvements

## 1.3.10

- Use mariadb_dump

## 1.3.9

- Improved backup of database
- Improved backup of media and finance files
- Fixed checkbox UI issues

## 1.3.8

- UI improvements
- Added ability to copy hidden workshop URLs
- Moved hidden status of workshops to separate flag
- Fixed access code/tickets flow

## 1.3.7

- Maintenance page is now responsive
- UI improvements
- Allow renaming of saved devices

## 1.3.6

- Support reverse proxies in identifying IP addresses
- Added option to have hidden workshops
- Improved SEO
- Added workshop sign in kiosk
- Moved to tales-from-a-dev/tailwind-merge-phpMoved to tales-from-a-dev/tailwind-merge-php
- Dependencies updated
- Admin users are no longer tracked in analytics

## 1.3.5

- Fixed scheduler updating 'Opens soon' workshops to 'Open' based on publish at date

## 1.3.4

- Pick lists are now saved automatically
- Added Artisan test/qa commands
- Fix styling on small checkboxes
- Added link to repo in footer
- Support for remembering email and devices
- Improved error handling of Altcha
- Improved layout of pick list on mobile
- Added inline support for ui.input component
- Fixed issue where pubished at for workshops wasnt being honored
- Workshop status banner now goes beyond card
- Changed 'Scheduled' to 'Opens Soon' status type

## 1.3.3

- Improved password manager support
- Improved styling checkboxes of invoice and quote editor

## 1.3.2

- Changing a quote ID will now redirect to the new ID
- Quote title is now shown in the quote list
- Fixed line item and quantity spacing/alignment in quotes and invoices
- Total columns now specify GST state

## 1.3.1

- Hide popup suggestions when selecting item
- Disable and show loader when saving pick lists
- Pluralize pick list items automatically
- Improved layout of BAS tables
- Improved wording in no tickets found email
- Fixed bug in creating quotes errored
- Fixed bug where address line 2 was not saved

## 1.3.0

- Added workshop pick list support
- Updated dependencies

## 1.2.4

- Improved the display of negative currency amounts

## 1.2.3

- Improved BAS reporting

## 1.2.2

- BAS report did not take refunds into account

## 1.2.1

- Pass altcha checks if config is invalid
- Prevent workshop changes if active tickets are present
- Improved mobile view of admin invoices, payment and audit log pages

## 1.2.0

- Added financial support to the website
- Added ticketing options to workshops
- Added email subscripton management from admin panel
- Do not show 'v' prefix in footer when a direct branch deployed

## 1.1.1

- Updated server information and auto updating logs

## 1.1.0

- Added server information admin page

## 1.0.11

- Fixed malformed delete email template
- Verified return type from browser for deletion messaging
- Fixed login message on account creation to be more accurate

## 1.0.10

- Fixed missing user-delete email template
- Fixed styling on error notifications

## 1.0.9

- Added in dev barryvdh/laravel-ide-helper
- Fixed error in UserDelete class

## 1.0.8

- Fix overzealous bot protection

## 1.0.7

- Fix subscription message exception
- Confirm account deletion by email

## 1.0.6

- Remove Discord links
- Fix subscription renderedAt initalization
- Fixed bad bot protection on register page
- Account: First, Surname and Phone are required only when the other fields are not empty

## 1.0.5

- Fix variable access exception

## 1.0.4

- Updated about page
- Updated dependencies
- Improved bot handling on subscription form
- Fix bot protection on register page

## 1.0.3

- Updated dependencies
- Added project notes

## 1.0.2

- Display version in footer.
