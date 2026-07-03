/**
 * Every endpoint the blog client calls, in one file — grouped by entity so
 * "what does this client talk to" is answerable by reading this file alone,
 * without hunting across `posts/`, `users/`, etc.
 */
export const blogUrls = {
  posts: {
    list: () => '/posts',
    detail: (id: number) => `/posts/${id}`,
    comments: (postId: number) => `/posts/${postId}/comments`,
  },
  users: {
    detail: (id: number) => `/users/${id}`,
  },
};
