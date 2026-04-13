---
title: 'Dokploy'
description:
  'A practical guide: from server installation to full deploy (with docker
  compose)'
---

# Deploying projects with Dokploy (compose)

## Server installation

Before you begin, make sure your server meets the minimum requirements:

| Parameter  | Minimum       |
| ---------- | ------------- |
| RAM        | 2 GB          |
| Disk       | 30 GB         |
| Open ports | 80, 443, 3000 |

1. **Switch to root**

   Installing Dokploy requires root privileges:

   ```sh
   sudo su
   ```

2. **Run the official installer**

   A single command installs Docker and Dokploy and starts the necessary
   containers:

   ```sh
   curl -sSL https://dokploy.com/install.sh | sh
   ```

3. **Open the control panel**

   Once installed, Dokploy is accessible at `<SERVER_IP>:3000`

## Configuring a domain for Dokploy

By default, Dokploy is only accessible via its IP address and port `3000`. For
convenience and security, it's best to switch to your own domain.

1. **Specify the domain in the settings**

   Go to `Dashboard → Settings → Web Server` and enter your subdomain. To avoid
   conflicts with your main domains, it is recommended to use a project-specific
   subdomain, like:

   ```
   dokploy.my-project.com
   ```

2. [**Disable IP-based access** _(recommended)_](//docs.dokploy.com/docs/core/installation#disable-access-via-ipport-optional-but-recommended)

   Once the domain has been configured and verified, you can remove direct
   access via `<SERVER_IP>:3000`:

   ```sh
   docker service update --publish-rm "published=3000,target=3000,mode=host" dokploy
   ```

   > [!DANGER]
   >
   > Run this command only after verifying that the domain is correctly
   > configured on the DNS provider's side. If the domain does not resolve, you
   > will lose access to the control panel.

   After that, the server will return a `404` error when accessed via IP, but
   everything works correctly via the domain.

## [Docker Compose](//docs.dokploy.com/docs/core/docker-compose)

Let's say we want to deploy a simple Symfony or Laravel application running in a
container. A typical setup includes services like `app`, `db`, and something
like `mailhog` for mail.

### Creating a project

On the dashboard page, click `Create Project` and enter a project name.

After creation, you will be automatically redirected to the project page. By
default, this will be the `production` environment, but you can add more — click
the arrow next to the project name.

To add services, click `Create Service → Compose` and fill in the required
fields.

To connect a repository, you first need to add a git provider. See the docs:
[GitHub setup](//docs.dokploy.com/docs/core/github).

> [!WARNING]
>
> Only the user who configured the git provider will be able to select a branch
> and compose file for the project. Other admin users will not have that access.

During setup you can choose the repository, branch, and `compose.yml` file.

> [!TIP]
>
> I recommend creating a `.dokploy` directory and storing environment-specific
> files there:
>
> ```
> .dokploy/
> ├── compose.dev.yml
> ├── compose.stage.yml
> └── compose.prod.yml
> ```

### Project structure in Dokploy

Dokploy stores each project at the following path:

```
/etc/dokploy/compose/<app>/
├── code/                    ← git clone (removed on redeploy)
│   ├── .dokploy/
│   │   └── compose.prod.yml ← your compose file
│   └── src/
└── files/                   ← persistent storage (logs, configs)
```

| Directory | Description        | Persists between deploys |
| --------- | ------------------ | ------------------------ |
| `code/`   | Project code (git) | ❌ Removed               |
| `files/`  | Persistent storage | ✅ Kept                  |

> [!WARNING]
>
> **The `code/` directory is fully removed on every redeploy.** Anything that
> needs to persist between deploys must be stored in `files/` or in a named
> volume.

### Paths in the compose file

If `compose.yml` is not in the project root, all paths are relative to its
location:

```yml [.dokploy/compose.prod.yml]
services:
  app:
    build:
      context: './..'
    volumes:
      - ./../docker/nginx/custom.conf:/etc/nginx/conf.d/default.conf
```

### Environment variables

Environment variables in Dokploy are **only available at container build time**
and are not automatically injected at runtime. To make them available during
execution, pass them explicitly via `compose.yml`.

#### Option 1 — pass each variable individually

```yml [.dokploy/compose.prod.yml]
services:
  app:
    environment:
      APP_KEY: ${APP_KEY}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: ${REDIS_HOST}
```

#### Option 2 — load the entire `.env` file

```yml [.dokploy/compose.prod.yml]
services:
  app:
    env_file:
      - .env
```

Dokploy automatically creates a `.env` file next to your compose file
(`/etc/dokploy/compose/<app>/`). The file itself won't be inside the container,
but the variables will be available in the environment. You can verify with:

```sh
printenv
```

> [!WARNING]
>
> After changing env variables, you need to rebuild the container — changes are
> not picked up automatically.

### Working with ports

All external access to containers is handled through Traefik. You should not
bind ports `80:80` or `443:443` — these are reserved for Traefik's traffic
routing. Instead, use `expose` to declare ports within the Docker network.

|               | `expose`                  | `ports`                    |
| ------------- | ------------------------- | -------------------------- |
| Accessibility | Docker network only       | Exposed on the host server |
| Use for       | Web services (`app`, API) | Databases, SSH services    |
| Traefik       | Routes traffic here       | Not needed                 |

```yml [.dokploy/compose.prod.yml]
services:
  app:
    expose:
      - '80'
      - '443'
```

> [!TIP]
>
> If you need to connect to a database from outside the server, use `ports`:
>
> ```yml [.dokploy/compose.prod.yml]
> services:
>   db:
>     ports:
>       - '3306'
> ```
>
> By default, Docker assigns a random host port on every redeploy, which breaks
> external connections (e.g. a database connection in PhpStorm). To keep the
> port stable, pin it via an env variable:
>
> ```yml [.dokploy/compose.prod.yml]
> services:
>   db:
>     ports:
>       - '${DB_EXTERNAL_PORT}:3306'
> ```
>
> Set `DB_EXTERNAL_PORT=33060` (or any free port) in Dokploy's env settings —
> the port will stay the same across redeploys.

### Volumes — persisting data

#### Named volumes — for databases

Docker manages the storage path. Use when data is only needed by the container
and external access is not required:

```yml [.dokploy/compose.prod.yml]
services:
  db:
    volumes:
      - db-data:/var/lib/mysql

volumes:
  db-data:
```

#### Bind mounts — for logs and configs

Use when you need access to files from outside the container:

```yml [.dokploy/compose.prod.yml]
services:
  app:
    volumes:
      # <host path>:<container path>
      - ../../files/var/log:/var/www/html/var/log
```

### Isolated environments

If multiple projects on the same server share service names (e.g. `db`), without
isolation they may end up on the same network and become visible to each other.

**Solution:** enable `Isolated Deployments` in Dokploy settings. Each project
will get its own unique network and containers won't conflict.

```yml [.dokploy/compose.prod.yml]
# Without isolation — add the network explicitly
networks:
  dokploy-network:
    external: true

# With isolation — Dokploy manages the network automatically,
# nothing needs to be added
```

> [!IMPORTANT]
>
> With isolation enabled, you no longer need to add `dokploy-network` to the
> compose file — traffic routing is handled automatically through the project's
> unique network.

### [Post-start hooks](//docs.docker.com/compose/how-tos/lifecycle/#post-start-hooks)

To run commands after the container starts (setting permissions, running
migrations) — use `post_start`:

```yml [.dokploy/compose.prod.yml]
services:
  app:
    post_start:
      - command:
          chown -R www-data:www-data /var/www/html/var && chmod -R 775
          /var/www/html/var
        user: root
```

> [!NOTE]
>
> Files mounted via bind mount are owned by `root` by default. If the
> application runs as a different user (e.g. `www-data`), fix permissions in
> `post_start`.

## Examples

A minimal example for running a Symfony project on Dokploy. Isolated deployments
must be enabled for this setup.

```yml [.dokploy/compose.dev.yml]
services:
  app:
    image: ghcr.io/maks-oleksyuk/notes-symfony:dev
    pull_policy: always
    restart: unless-stopped
    expose:
      - 8080
      - 8443
    env_file:
      - .env
    environment:
      APP_ENV: dev
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - ../../files/var/log:/var/www/html/var/log
    post_start:
      - command:
          chown -R www-data:www-data /var/www/html/var && chmod -R 775
          /var/www/html/var
        user: root
      - command: task init
        user: www-data
        working_dir: /var/www/html

  db:
    image: mariadb:11.8
    restart: unless-stopped
    environment:
      MARIADB_USER: notes
      MARIADB_DATABASE: symfony
      MARIADB_PASSWORD: ${DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    expose:
      - 3306
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ['CMD', 'healthcheck.sh', '--connect', '--innodb_initialized']
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

volumes:
  db_data:
```
