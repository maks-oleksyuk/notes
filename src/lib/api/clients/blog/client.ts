import { HttpClient } from '../../core';

/**
 * Client for the JSONPlaceholder Blog API.
 */
export const blogApi = new HttpClient('https://jsonplaceholder.typicode.com', {
  next: {
    revalidate: 60,
    tags: ['blog'],
  },
  onRequest: (options) => {
    console.log(`🚀 [Blog API] ${options.method} ${options.path}`);
  },
  onResponse: (response) => {
    // Next.js adds this header to help us track cache status
    const nextCache = response.headers.get('x-nextjs-cache');
    const cacheStatus = nextCache
      ? `📦 CACHE: ${nextCache}`
      : response.duration < 40
        ? '📦 LIKELY CACHE'
        : '🌐 NETWORK';

    console.log(
      `✅ [Blog API] ${response.status} | ${response.duration}ms | ${cacheStatus}`,
    );
  },
});

// Types for the blog
export interface Post {
  userId: number;
  id: number;
  title: string;
  body: string;
}

export interface Comment {
  postId: number;
  id: number;
  name: string;
  email: string;
  body: string;
}

export interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  phone: string;
  website: string;
  company: { name: string; catchPhrase: string; bs: string };
  address: {
    street: string;
    suite: string;
    city: string;
    zipcode: string;
  };
}

// API functions for the blog
export async function getPosts(page = 1, limit = 10) {
  return blogApi.get<Post[]>('/posts', {
    params: { _page: page, _limit: limit },
  });
}

export async function getPost(id: number) {
  return blogApi.get<Post>(`/posts/${id}`);
}

export async function getPostComments(postId: number) {
  return blogApi.get<Comment[]>(`/posts/${postId}/comments`);
}

export async function getUser(id: number) {
  return blogApi.get<User>(`/users/${id}`, {
    next: { revalidate: 3600 }, // Overriding default for users
  });
}
