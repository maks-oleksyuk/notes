import { queryOptions } from '@tanstack/react-query';

import { getUser } from './requests';

export const usersQueries = {
  all: () => ['users'] as const,

  detail: (id: number) =>
    queryOptions({
      queryKey: [...usersQueries.all(), 'detail', id] as const,
      queryFn: async ({ signal }) => (await getUser(id, { signal })).data,
      // Author profiles change rarely — a longer staleTime than posts.
      staleTime: 5 * 60_000,
    }),
};
