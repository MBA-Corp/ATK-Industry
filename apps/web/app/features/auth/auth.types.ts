export interface Permission {
  code: string;
  name: string;
}

export interface Role {
  id: number;
  code: string;
  name: string;
  permissions?: Permission[];
}

export interface CurrentUser {
  id: number;
  name: string;
  username: string;
  email: string | null;
  is_active: boolean;
  roles: string[];
  permissions: string[];
}

export interface LoginInput {
  username: string;
  password: string;
}

export interface DataResponse<T> {
  data: T;
}
