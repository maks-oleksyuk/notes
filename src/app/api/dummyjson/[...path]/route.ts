import { dummyJsonServerApi } from '@/lib/api/dummyjson/server-client';
import { toNextJsProxyHandler } from '@/lib/http-client/next-proxy';

export const dynamic = 'force-dynamic';

export const { GET, POST, PATCH, PUT, DELETE } =
  toNextJsProxyHandler(dummyJsonServerApi);
