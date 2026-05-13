/**
 * JSONPlaceholder API endpoints.
 * Base: https://jsonplaceholder.typicode.com
 * Free, no API key, supports pagination via _page & _limit.
 */
export const BLOG_URLS = {
  posts: '/posts',
  post: (id: number) => `/posts/${id}`,
  comments: (postId: number) => `/posts/${postId}/comments`,
  users: '/users',
  user: (id: number) => `/users/${id}`,
} as const;
