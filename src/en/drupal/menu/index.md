---
title: Menu Api
---

# ☰ Menu Api

## Overview

Menus are a collection of links that are used to navigate a Drupal website. The
core Menu UI module provides an interface to control and customize the menu
system. The Menu API provides a number of functions that can be used to create,
edit, and delete menus. It also provides functions that can be used to add and
remove links from menus.

The Menu API is a powerful tool that can be used to create complex menus. It can
be used to create menus that are based on user roles, themes, or other criteria.
The Menu API can also be used to create dynamic menus that are generated based
on the content of a Drupal website.

## Menu Links

Each menu can have multiple links structured hierarchically in a tree with a
maximum depth of `9` links. The ordering of menu links can be easily
accomplished through the user interface or by using menu link weights, if
defined in the code.

Menu links can also be content objects. Links created using the user interface
are saved as objects because they are considered content. This works like this:
for each [`MenuLinkContent`] entity created, a derived plugin is created. Menu
links have several properties, including path or route. When created through the
user interface, the path can be external or internal, or refer to an existing
resource (for example, a user or piece of content). When you create them
programmatically, you usually use a route.

If the module wants to provide links to menus, they are defined in the module's
static file - `MODULE_NAME.links.menu.yml`, and can be changed by other modules
using the [`menu_links_discovered_alter()`] hook.

The file describes links with the following parameters:

| Key                   | Description                                                                                                                         |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `title`\*             | The text to be displayed for the menu item                                                                                          |
| `title_context`       | Adds a context for translation                                                                                                      |
| `description`         | The description of the menu item (by default, set as the title attribute on the resulting link tag)                                 |
| `description_context` | Adds a context for translation                                                                                                      |
| `route_name`          | The route name that the link points to.<br> If this is not set, the `url` key must be set                                           |
| `url`                 | Used for external links. This must be a fully qualified URL.<br> If this is not set, the `route_name` key must be set               |
| `parent`              | Identifier of the parent menu item.<br> Can be used to indicate that this link is a child of another (so you can build a hierarchy) |
| `weight`              | Relative weight of the menu item, sort by ASC or by name (default = `0`)                                                            |
| `enabled`             | Whether or not the menu should be enabled by default (0/1) (default = `1`)                                                          |
| `route_parameters`    | For routes with dynamic components (`entity.user.canonical`), this value must be provided to build the link                         |
| `menu_name`           | The machine name of the menu to which the menu item should be added (default = `administration`)                                    |
| `options`             | A series of options to be used when rendering the menu link. (see [\Drupal\Core\Url::fromUri()])                                    |

<!-- prettier-ignore-start -->
[`MenuLinkContent`]: //git.drupalcode.org/project/drupal/-/blob/HEAD/core/modules/menu_link_content/src/Entity/MenuLinkContent.php
[`menu_links_discovered_alter()`]: //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Menu/menu.api.php#221-267
[\Drupal\Core\Url::fromUri()]: //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Url.php#L248-266
<!-- prettier-ignore-end -->
