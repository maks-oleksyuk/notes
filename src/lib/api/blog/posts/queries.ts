import { queryOptions } from '@tanstack/react-query';

import { getPost, getPostComments, getPosts } from './requests';

export interface PostsPage {
  posts: Awaited<ReturnType<typeof getPosts>>['data'];
  totalCount: number;
}

/**
 * `queryOptions` factories for the posts entity only — no author names, no
 * page composition (that's feature-specific and lives with the page that
 * needs it, e.g. `app/blog-example/queries.ts`). Same object shared between
 * a server `fetchQuery` (RSC, no hook) and a client `useQuery`.
 */
export const postsQueries = {
  all: () => ['posts'] as const,

  list: (page = 1, limit = 10) =>
    queryOptions({
      queryKey: [...postsQueries.all(), 'list', { page, limit }] as const,
      queryFn: async ({ signal }): Promise<PostsPage> => {
        const response = await getPosts(page, limit, { signal });
        // JSONPlaceholder returns the total in a header; real APIs might put
        // it in the response body instead.
        const totalCount = Number(response.headers.get('X-Total-Count')) || 0;
        return { posts: response.data, totalCount };
      },
      staleTime: 60_000,
    }),

  detail: (id: number) =>
    queryOptions({
      queryKey: [...postsQueries.all(), 'detail', id] as const,
      queryFn: async ({ signal }) => (await getPost(id, { signal })).data,
    }),

  comments: (postId: number) =>
    queryOptions({
      queryKey: [...postsQueries.all(), 'comments', postId] as const,
      queryFn: async ({ signal }) =>
        (await getPostComments(postId, { signal })).data,
    }),
};
