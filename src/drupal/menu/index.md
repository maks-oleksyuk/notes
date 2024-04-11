---
title: Menu API
---

# ☰ Menu API

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

| Key                   | Description                                                                                                                     |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `title`\*             | The text to be displayed for the menu item                                                                                      |
| `title_context`       | Adds a context for translation                                                                                                  |
| `description`         | The description of the menu item (by default, set as the title attribute on the resulting link tag)                             |
| `description_context` | Adds a context for translation                                                                                                  |
| `route_name`          | The route name that the link points to. If this is not set, the `url` key must be set                                           |
| `url`                 | Used for external links. This must be a fully qualified URL. If this is not set, the `route_name` key must be set               |
| `parent`              | Identifier of the parent menu item. Can be used to indicate that this link is a child of another (so you can build a hierarchy) |
| `weight`              | Relative weight of the menu item, sort by ASC or by name (default = `0`)                                                        |
| `enabled`             | Whether or not the menu should be enabled by default (0/1) (default = `1`)                                                      |
| `route_parameters`    | For routes with dynamic components (`entity.user.canonical`), this value must be provided to build the link                     |
| `menu_name`           | The machine name of the menu to which the menu item should be added (default = `administration`)                                |
| `options`             | A series of options to be used when rendering the menu link. (see [`\Drupal\Core\Url::fromUri()`])                              |

```yml
entity.menu.collection:
  title: Menus
  description: 'Manage menus and menu links.'
  route_name: entity.menu.collection
  parent: system.admin_structure
```

## Local task links (tabs)

Local tasks are groups of related routes. Local tasks are usually rendered as a
group of tabs. If you've ever visited a node page and clicked on the edit link,
you've clicked a local task link to reach the edit form. These links are also
defined in a static YAML file named `MODULE_NAME.links.task.yml`.

| Key                | Description                                                                          |
| ------------------ | ------------------------------------------------------------------------------------ |
| `title`\*          | The static title for the local task                                                  |
| `title_context`    | Adds a context for translation                                                       |
| `route_name`\*     | The name of the route this task links to                                             |
| `route_parameters` | Parameters for route variables when generating a link                                |
| `base_route`       | The route name where the root tab appears                                            |
| `parent_id`        | The plugin ID of the parent tab (or NULL for the top-level tab)                      |
| `weight`           | The weight of the tab                                                                |
| `options`          | The default link options. (see [`\Drupal\Core\Url::fromUri()`])                      |
| `class`            | Default class for local task implementations ([`Drupal\Core\Menu\LocalTaskDefault`]) |

```yml
entity.menu.edit_form:
  title: 'Edit menu'
  route_name: entity.menu.edit_form
  base_route: entity.menu.edit_form

entity.menu.collection:
  title: 'List'
  route_name: entity.menu.collection
  base_route: entity.menu.collection
```

> [!WARNING]
>
> Local tabs will not be displayed if there are less than 2 of them, this is
> influenced by the number and access rights.

## Action links

Local actions are used for operations such as adding a new item on a page that
lists items of some type. Local actions are usually rendered as buttons. Action
links allow module developers to provide particular actions for working with
their data structures. An example of this can be seen on the content type
administration screen (`/admin/structure/types`) with the <kbd>Add content</kbd>
type button. Actions are specified in yet another YAML file with a particular
naming convention, `MODULE_NAME.links.action.yml`.

| Key                | Description                                                                                   |
| ------------------ | --------------------------------------------------------------------------------------------- |
| `title`\*          | The static title for the local action                                                         |
| `title_context`    | Adds a context for translation                                                                |
| `route_name`\*     | The route name that the link points to                                                        |
| `route_parameters` | Defining the parameters that route requires                                                   |
| `options`          | A series of options to be used when rendering the link. (see [`\Drupal\Core\Url::fromUri()`]) |
| `weight`           | The weight of the local action (default = `0`)                                                |
| `appears_on`\*     | The route names where this local action appears                                               |
| `class`            | Default class for local action implementations ([`Drupal\Core\Menu\LocalActionDefault`])      |

```yml
menu_ui.menu_add:
  title: 'Add menu'
  route_name: menu_ui.menu_add
  appears_on:
    - menu_ui.overview_page
```

## Contextual links

Contextual links are actions that are related to sections of rendered output,
and are usually rendered as a pop-up list of links. The Contextual Links module
handles the gathering and rendering of contextual links. Contextual links
provide shortcuts to common administrator tasks. For example, when viewing a
node, common actions may include editing or deleting the content. These routes
are listed in a YAML file with the name `MODULE_NAME.links.contextual.yml`. In
addition to the YAML file ax contextual link needs to add a #contextual_links
element to the render array where the link needs to appear.

| Key                | Description                                                                                    |
| ------------------ | ---------------------------------------------------------------------------------------------- |
| `title`\*          | The static title for the contextual link                                                       |
| `title_context`    | Adds a context for translation                                                                 |
| `route_name`\*     | The route name that the link points to                                                         |
| `route_parameters` | Defining the parameters that route requires                                                    |
| `options`          | The default link options (see [`\Drupal\Core\Url::fromUri()`])                                 |
| `group`\*          | The contextual links group                                                                     |
| `weight`           | The weight of the link (default = `0`)                                                         |
| `class`            | Default class for contextual link implementations ([`Drupal\Core\Menu\ContextualLinkManager`]) |

```yml
entity.menu.edit_form:
  title: 'Edit menu'
  route_name: 'entity.menu.edit_form'
  group: menu
```

## Resources

- [Drupal Menu API](//drupal.org/docs/drupal-apis/menu-api)
- [Concept: Menu | Basic Page Management](//drupal.org/docs/user_guide/en/menu-concept.html)
- [Overview: Menu Links in a Module for Drupal 8, 9, and 10](//drupalize.me/tutorial/overview-menu-links-module)
- [Providing module-defined local tasks | Menu API | Drupal Wiki guide on Drupal.org](//drupal.org/node/2122253)
- [Drupal 10 Development Cookbook - Third Edition](//packtpub.com/product/drupal-10-development-cookbook-third-edition/9781803234960)
- [Drupal 8: Создание собственного раздела на странице конфигурации](//niklan.net/blog/137)

<!-- prettier-ignore-start -->
[`MenuLinkContent`]:                        //git.drupalcode.org/project/drupal/-/blob/HEAD/core/modules/menu_link_content/src/Entity/MenuLinkContent.php
[`menu_links_discovered_alter()`]:          //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Menu/menu.api.php#221-267
[`\Drupal\Core\Url::fromUri()`]:            //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Url.php#L248-266
[`Drupal\Core\Menu\LocalTaskDefault`]:      //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Menu/LocalTaskDefault.php
[`Drupal\Core\Menu\LocalActionDefault`]:    //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Menu/LocalActionDefault.php
[`Drupal\Core\Menu\ContextualLinkManager`]: //git.drupalcode.org/project/drupal/-/blob/HEAD/core/lib/Drupal/Core/Menu/ContextualLinkManager.php
<!-- prettier-ignore-end -->
