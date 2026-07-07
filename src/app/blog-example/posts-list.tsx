'use client';

import { useQuery } from '@tanstack/react-query';

import { blogQueries } from '@/lib/api/blog';

interface PostsListProps {
  page: number;
}

/**
 * Client Component — same `blogQueries.pageData(page)` the RSC parent
 * prefetched (see `page.tsx`). The `HydrationBoundary` above this in the tree
 * already seeded the cache, so this `useQuery` resolves from cache on first
 * render — no extra network round-trip, no loading flash. From here on
 * (navigation, refetch, background revalidation) it's a normal client-side
 * TanStack Query, unrelated to the RSC render that produced the initial data.
 */
export function PostsList({ page }: PostsListProps) {
  const { data } = useQuery(blogQueries.pageData(page));

  // `data` is only possibly undefined if this ever renders without the
  // hydration boundary above it (or the query errored, which `page.tsx`
  // already turned into a redirect/error page before rendering this at all).
  if (!data) return null;

  return (
    <ul className='space-y-4'>
      {data.posts.map((post) => (
        <li key={post.id} className='border rounded-lg p-4 space-y-1'>
          <h2 className='font-semibold'>{post.title}</h2>
          <p className='text-sm text-gray-500'>
            by {data.users[post.userId] ?? `User #${post.userId}`}
          </p>
          <p className='text-gray-700 text-sm line-clamp-2'>{post.body}</p>
        </li>
      ))}
    </ul>
  );
}
