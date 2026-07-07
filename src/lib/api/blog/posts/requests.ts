import { blogApi } from '../client';
import { blogUrls } from '../urls';

import type { RequestOverrides } from '../client';
import type { Comment, Post } from './types';

/** Raw request functions — one HTTP call each, throw-style (see
 * core/safe.ts for the error-as-value alternative). No caching here; that's
 * `queries.ts` in this same folder. */

export async function getPosts(
  page = 1,
  limit = 10,
  overrides?: RequestOverrides,
) {
  return blogApi.get<Post[]>(blogUrls.posts.list(), {
    ...overrides,
    params: { _page: page, _limit: limit },
  });
}

export async function getPost(id: number, overrides?: RequestOverrides) {
  return blogApi.get<Post>(blogUrls.posts.detail(id), overrides);
}

export async function getPostComments(
  postId: number,
  overrides?: RequestOverrides,
) {
  return blogApi.get<Comment[]>(blogUrls.posts.comments(postId), overrides);
}
