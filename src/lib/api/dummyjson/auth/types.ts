export interface LoginCredentials {
  username: string;
  password: string;
  /** Access token lifetime; dummyjson defaults to 60 if omitted. */
  expiresInMins?: number;
}

export interface AuthTokens {
  accessToken: string;
  refreshToken: string;
}

export interface AuthUser {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  gender: string;
  image: string;
}

export type LoginResponse = AuthUser & AuthTokens;
