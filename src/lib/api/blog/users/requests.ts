import { blogApi } from '../client';
import { blogUrls } from '../urls';

import type { RequestOverrides } from '../client';
import type { User } from './types';

export async function getUser(id: number, overrides?: RequestOverrides) {
  return blogApi.get<User>(blogUrls.users.detail(id), {
    ...overrides,
    // `next` replaces the client default wholesale (shallow merge in
    // mergeOptions), so the 'blog' tag must be restated here — without it
    // revalidateTag('blog') would skip user fetches.
    next: { revalidate: 3600, tags: ['blog'] },
  });
}
