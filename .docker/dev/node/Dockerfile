# todo
FROM wodby:26 AS base

RUN sh -c "$(curl -s --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin > /dev/null 2>&1

RUN npm install -g pnpm@11.9.0

USER node

WORKDIR /app

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile --color=always --config.strictDepBuilds=false

COPY --chown=node:node . .

RUN task build

EXPOSE 3000

CMD ["task", "start"]
