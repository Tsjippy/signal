# Changelog
## [Unreleased] - yyyy-mm-dd

### Added

### Changed

### Fixed

### Updated

## [10.5.5] - 2026-06-23


### Fixed
- hook names

## [10.5.4] - 2026-06-23


### Changed
- addElementFunction

### Fixed
- phonenumbers bug

## [10.5.3] - 2026-06-23


## [10.5.2] - 2026-06-23


### Changed
- implemented db caching
- implemented db caching
- replaced wpdb->update with updateDbFunction

### Fixed
- send e-mail by signal

## [10.5.1] - 2026-06-21


## [10.5.0] - 2026-06-21


### Added
- command history log to be able to track down reasons for rate limit issues

### Fixed
- check for token if rate limited

## [10.4.9] - 2026-06-19


### Added
- request sanitazion

## [10.4.8] - 2026-06-18


### Changed
- hook and filter name update
- hook and filter name update
- prefix all hooks with plugin name

### Fixed
- sent message table

## [10.4.5] - 2026-06-15


## [10.4.4] - 2026-06-15


### Changed
- removed duplicate captcha function

## [10.4.3] - 2026-06-15


### Fixed
- bugs

## [10.4.2] - 2026-06-13


### Added
- transform string to clickable signal number

### Changed
- removed smiley script
- prefix meta key in get_users

### Fixed
- shared code loader
- activation hook

## [10.4.1] - 2026-06-11


### Added
- user, post and rest_meta prefixing

### Changed
- prefixed post metas and shortcodes

### Fixed
- prefix meta_query

## [10.4.0] - 2026-06-09


### Added
- usage of wpdb->prepare for all queries
- shared functionality loader

### Changed
- comply to coding standards
- code layout
- namespaced all constants
- sanitize all posts and get vars
- moved inline js to js file
- moved inline style to scss file

### Fixed
- spacing problem
- space before dot bug
- use pluginversion

## [10.3.9] - 2026-06-03


### Added
- echo escaping

### Changed
- addSaveButton with echo param

### Fixed
- do not use wp_strip_all_tags

## [10.3.8] - 2026-06-01


### Added
- processing command queue sleep based on pending amount

### Changed
- merged hooks.md into readme.md

## [10.3.7] - 2026-06-01


### Changed
- loading libraries is now done in shared-functionality plugin

## [10.3.6] - 2026-06-01


### Added
- skip signal-cli version 0.14.4.1

### Changed
- js update

## [10.3.5] - 2026-05-30


### Changed
- global var rename
- do not store get_plugin_data in global variable

## [10.3.4] - 2026-05-29


### Added
- wp_unslash

## [10.3.3] - 2026-05-28


### Fixed
- admin menu bug

## [10.3.2] - 2026-05-28


### Fixed
- do not wait on signal mail

## [10.3.1] - 2026-05-28


### Added
- js dependency

### Fixed
- empty main-tab param bug

## [10.3.0] - 2026-05-28


### Fixed
- ?? bug

## [10.2.9] - 2026-05-27


### Fixed
- empty groups bug

## [10.2.8] - 2026-05-26


### Changed
- no jsonrpc on windows

## [10.2.7] - 2026-05-26


### Fixed
- phonenumber bug

## [10.2.6] - 2026-05-26


### Fixed
- java version check

## [10.2.5] - 2026-05-25


### Changed
- html to domelements
- html tp dpm elements in admin menu

### Fixed
- no rpc on windows

## [10.2.4] - 2026-05-25


### Changed
- ony update signal-cli after 5 days

### Fixed
- windows install

## [10.2.3] - 2026-05-23


### Fixed
- dev problems

## [10.2.2] - 2026-05-23


### Fixed
- path not found bug
- named param bug
- quoteTimestamp named parameter
- getResultsFromDb bug
- only try to update result if result is not an object
-  send reaction result

## [10.2.1] - 2026-05-22


### Fixed
- bugs

## [10.2.0] - 2026-05-21


### Added
- Ai model response

### Fixed
- getUserStatus command

## [10.1.9] - 2026-05-20


### Changed
- renmae functions to matching signal-cli command names

### Fixed
- rate limit when null

## [10.1.8] - 2026-05-20


### Fixed
- admin urls

## [10.1.7] - 2026-05-20


### Added
- post processing after queue

### Changed
- code shuffle
- get token in 2 formats

## [10.1.6] - 2026-05-19


### Added
- ratelimit function
- get rate limit status from db

### Changed
- do no continue when no queue
- do not update result if no result
- improved command queue processing

### Fixed
- command queue processig

## [10.1.5] - 2026-05-16


### Fixed
- rate limit issues

## [10.1.4] - 2026-05-16


### Changed
- code shuffle
- better errors

### Fixed
- echo error

## [10.1.3] - 2026-05-15


### Changed
- run missed actions from cron

## [10.1.2] - 2026-05-15


### Fixed
- initializing bug

## [10.1.1] - 2026-05-14


### Changed
- date( to gmdate(

## [10.1.0] - 2026-05-12


### Changed
- permission callback for rest api

### Fixed
- daemon

## [10.0.8] - 2026-05-11


### Added
- 'signal-admin-display-name' filter

### Changed
- removed admin login for cron

## [10.0.6] - 2026-05-07


### Changed
- replaced sweetalert

## [10.0.5] - 2026-05-06


### Changed
- make sure table columns are updated

## [10.0.4] - 2026-05-05


### Fixed
- admin menu

## [10.0.2] - 2026-05-04


## [10.0.1] - 2026-05-03


### Changed
- removed the redirection at activation as it is done by the share plugin
- removed vaccinations
- use shared github workflows

## [10.0.0] - 2026-05-01


### Added
- messaging queue

### Changed
- implemented wp_get_environment_type(
- module to plugin
- recurrence selector code
- exclude .vscode from releases
- updated github workflow versions

### Fixed
- updating signal
- registration issues
- refresh groups
- install on Windows
- changing avatar
- install on Windows

## [8.4.5] - 2026-02-05


### Added
- you can now select to which group to send new content to

### Changed
- label

## [8.4.4] - 2025-12-01


### Changed
- mkdir replace

## [8.4.3] - 2025-11-26


### Changed
- lib update

## [8.4.2] - 2025-11-24


### Added
- support for Local

### Changed
- formresults to submission
- signal icon to node based html
- composer updated
- dropped support for DBUS

### Fixed
- php8.4 complicance

## [8.4.1] - 2025-11-03


### Changed
- stop listening to events if we have a match

## [8.4.0] - 2025-10-30


### Changed
- feed the original message to the deamon response filter, not the lowercase one
- use upgrade.php not install-helper.php

## [8.3.9] - 2025-10-20


### Changed
- filter update

## [8.3.7] - 2025-10-13


### Changed
- classnames
- data attribute names

### Fixed
- bugs

## [8.3.6] - 2025-10-06


### Changed
- classname

## [8.3.5] - 2025-09-26


### Changed
- cleaner admin js
- classnames replace _ with -

## [8.3.4] - 2025-09-22


### Fixed
- nice selects

## [8.3.3] - 2025-09-16


### Fixed
- upgrade problem

## [8.3.2] - 2025-09-10


### Changed
- synchronous signal actions to prevent overload

### Fixed
- reply to metioned messaged

## [8.3.1] - 2025-08-21


### Added
- send image from base64 string
- retrieve messages by phonenumber

### Changed
- book of the day message title on seperate line

### Fixed
- send book picture

## [8.3.0] - 2025-08-18


### Added
- actions
- send book of the day

### Changed
- book of the day message

### Fixed
- bug in book signal message

## [8.2.8] - 2025-07-14


### Fixed
- retry queue

## [8.2.7] - 2025-07-02


### Changed
- improved daemon logging
- code reordering

### Fixed
- empty deamon response
- enqueation of js

## [8.2.6] - 2025-05-08


### Changed
- increased sleep while resending signal messages
- updated docs
- check for bad phonenumber

### Fixed
- return types

## [8.2.5] - 2025-03-21


### Added
- send signal message when sending e-mail

## [8.2.4] - 2025-03-20


### Changed
- typos

### Fixed
- retry failed sigal messages

## [8.2.3] - 2025-02-13


### Changed
- module hooks now include module slug

## [8.2.2] - 2025-02-12


### Changed
- better layout parsing

## [8.2.1] - 2025-02-11


### Changed
- sim_module_updated filter to new format

## [8.2.0] - 2025-02-09


### Changed
- after update hook

## [8.1.9] - 2024-12-01


### Added
- replace html tags with signal layout

## [8.1.8] - 2024-11-29


### Fixed
- signal bot
- signal styling issue

## [8.1.7] - 2024-11-28


### Changed
- removed style argument

## [8.1.6] - 2024-11-22


### Changed
- removed anonymous functions

## [8.1.5] - 2024-11-20


### Changed
- removed anonymous functions

## [8.1.4] - 2024-10-30


### Fixed
- style constant problem
- isRegistered command

## [8.1.3] - 2024-10-17


### Changed
- block

### Fixed
- namespace error

## [8.1.2] - 2024-10-17


## [8.1.1] - 2024-10-17


### Changed
- readme

## [8.1.0] - 2024-10-11


### Added

### Changed
- redering of asset urls

### Fixed

## [8.0.22] - 2024-10-02


### Added

### Changed

### Fixed