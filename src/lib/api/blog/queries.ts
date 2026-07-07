import { queryOptions } from '@tanstack/react-query';

import { safe } from '@/lib/http-client/core';

import { getPosts, postsQueries } from './posts';
import { getUser, usersQueries } from './users';

import type { Post } from './posts';

const POSTS_PER_PAGE = 5;

export interface BlogPageData {
  posts: Post[];
  // Plain object, not a `Map` — TanStack Query dehydrates query data through
  // `JSON.stringify` for the RSC → client handoff, and a `Map` doesn't survive
  // that (it'd come out the other side as `{}`).
  users: Record<number, string>;
  totalPages: number;
}

/**
 * The one queries namespace for this client — `blogQueries.posts.*` and
 * `blogQueries.users.*` are `postsQueries`/`usersQueries` nested under it, so
 * call sites only ever import `blogQueries` and never have to juggle which
 * differently-named object belongs to which entity. `pageData` is the one
 * query that doesn't belong to a single entity — see below.
 */
export const blogQueries = {
  posts: postsQueries,
  users: usersQueries,

  all: () => ['blog', 'pageData'] as const,

  /**
   * Combines the posts and users entities into what the `/blog-example` page
   * needs — posts + resolved author names + pagination total, in one cache
   * entry. Cross-entity composition still lives here, not next to the
   * component: every request this client makes belongs in `clients/blog/`,
   * so a page's data shape doesn't have to be rebuilt near its UI.
   */
  pageData: (page = 1) =>
    queryOptions({
      queryKey: [...blogQueries.all(), { page }] as const,
      queryFn: ({ signal }) => fetchPageData(page, signal),
      staleTime: 60_000,
    }),
};

async function fetchPageData(
  page: number,
  signal: AbortSignal,
): Promise<BlogPageData> {
  // Posts are the query's reason to exist — let a failure throw and become
  // the query's `error` (TQ's job to surface it), same as any other queryFn.
  const response = await getPosts(page, POSTS_PER_PAGE, { signal });

  const totalCount = Number(response.headers.get('X-Total-Count')) || 0;
  const totalPages = Math.ceil(totalCount / POSTS_PER_PAGE);

  // Author names are decoration — `safe()` keeps one failed lookup from
  // failing the whole page.
  const userIds = [...new Set(response.data.map((p) => p.userId))];
  const userResults = await Promise.all(
    userIds.map((id) => safe(getUser(id, { signal }))),
  );

  const users: Record<number, string> = {};
  for (const result of userResults) {
    if (result.error) continue;
    users[result.data.id] = result.data.name;
  }

  return { posts: response.data, users, totalPages };
}
