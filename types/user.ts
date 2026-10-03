export interface Role {
  id: number;
  name: string;
  slug: string;
}

export interface User {
  id: number;

  name: string;
  username: string;

  email: string;

  phone: string | null;
  avatar: string | null;

  status: boolean;

  role?: Role;

  created_at?: string;
  updated_at?: string;
}

export interface AdminRole extends Role {
  description: string | null;
  status: boolean;
  users_count?: number;
}
