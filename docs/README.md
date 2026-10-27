# Move content and users

**Transfer Content, Comments and Likes from one User or Space to another.**

**Transfer Users from one Group or Space to another.**

## Overview

Use cases:
- A user has deleted their account and later recreated it and wants to recover their content.
- A user closes their account but wants to transfer their content to another user first.
- A user accidentally creates 2 accounts and wants to merge the content created in one account into the other.
- Merging the content of a space into another
- Transferring users from a Space or Group to another

## Administration

- Go to `Administration` -> `Users` or `Space` -> `Move content`

### Moving a Space

When moving a Space into another, choose what to move (at least one option is required):
- **Move the Space content**: all content (with comments, likes and files) and topics are moved to the target Space. Modules needed by this content are enabled on the target Space. Topics with the same name are merged, and wiki pages having the same title as an existing one are renamed.
- **Move the Space members**: all members except the owner are removed from the source Space, and those missing from the target Space are added with the same role.

If the content is moved without the members, data such as calendar event participations or task assignments may be deleted.

If the members are moved without the content, the content stays in the source Space: the removed members may no longer access it if the Space is private, and data such as their task assignments in the source Space are deleted.

## Pricing

This module is free, but the result of a lot of work for design and maintenance over time.

If it's useful to you, please consider [making a donation](https://www.cuzy.app/checkout/donate/) or [participating in the code](https://github.com/cuzy-app/move-content). Thanks!

## Repository

https://github.com/cuzy-app/move-content

## Publisher

[CUZY.APP](https://www.cuzy.app/)

## Licence

[GNU AGPL](https://github.com/cuzy-app/move-content/blob/master/docs/LICENCE.md)
