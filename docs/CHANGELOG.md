Changelog
=========

Unreleased
--------------------
- Enh: Add GitHub HumHub PHP workflows (tests & CS fixer)

2.1.3 (Sept 21, 2024)
--------------------
- Enh: Updated translations

2.1.2 (April 29, 2024)
--------------------
- Enh: When moving a content from a user to another, don't change the content update date (to keep the same stream order)

2.1.1 (April 5, 2024)
--------------------
- Chg: Repository URL from https://github.com/cuzy-app/humhub-modules-move-content to https://github.com/cuzy-app/move-content
- Fix: Compatibility with HumHub 1.16

2.1.0 (March 10, 2024)
--------------------
- Enh: Possibility to move Users from one Group to another

2.0.1 (March 3, 2024)
--------------------
- Enh: Move topics from the source to the target space and remove duplicates
- Fix: Wiki pages are added "Conflict with same page title:" to the title even if no conflict

2.0.0 (February 27, 2024)
--------------------
- Enh: Transfer content from a space to another (thanks [@JK742020](https://github.com/JK742020) for financing the development)
- Enh: Add [Reactions](https://marketplace.humhub.com/module/reaction) to the list of content addons to move

1.1.1 (November 22, 2023)
--------------------
- Fix #3: Crash when submitting the form allowing to select the users

1.1.0 (July 25, 2023)
--------------------
- Enh: Added config page (for admins)
- Chg: Job don't retry anymore if error, but adds the error message to the logging

1.0.2 (November 28, 2022)
--------------------

- Enh: Added likes to content addons movable
- Chg: Minimal HumHub version is now 1.13

1.0.1 (November 28, 2022)
--------------------

- Fix: Content Addons (such as comments) are moved in addition to the content

1.0 (November 21, 2022)
--------------------

- Enh: Initial commit
