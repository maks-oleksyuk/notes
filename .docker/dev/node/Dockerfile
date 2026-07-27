ARG NODE_VERSION=26

FROM wodby/node:${NODE_VERSION} AS base

RUN npm install -g corepack@latest && corepack enable

WORKDIR /app

FROM base AS builder

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml ./

RUN pnpm install --frozen-lockfile --color=always

COPY --chown=node:node . .

RUN FORCE_COLOR=1 pnpm exec next build

FROM wodby/node:${NODE_VERSION} AS runner

ENV NODE_ENV=production

WORKDIR /app

COPY --from=builder --chown=node:node /app/public ./public
COPY --from=builder --chown=node:node /app/.next/standalone ./
COPY --from=builder --chown=node:node /app/.next/static ./.next/static

USER node

EXPOSE 3000

ENV PORT=3000 HOSTNAME=0.0.0.0

CMD ["node", "server.js"]
