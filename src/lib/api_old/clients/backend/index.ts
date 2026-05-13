// ── Backend API ───────────────────────────────────────────────────
// Single entry point for all backend operations
//
// Configure via .env:
//   NEXT_PUBLIC_API_URL=http://localhost:3001/api
//   NEXT_PUBLIC_API_TOKEN=your-token

// Low-level client
export { backendClient, del, get, patch, post, put } from './client';
// High-level functions
export {
  createPost,
  deletePost,
  getPost,
  getPostComments,
  getPosts,
  publishPost,
  unpublishPost,
  updatePost,
} from './posts';
// Domain types
export type {
  ApiError,
  Comment,
  CreatePostDto,
  PaginatedResponse,
  Post,
  UpdatePostDto,
  UpdateUserDto,
  User,
} from './types';

export {
  getCurrentUser,
  getUser,
  getUsers,
  updateProfile,
  updateUser,
  uploadAvatar,
} from './users';
