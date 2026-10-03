export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  image_path?: string | null;
  sort_order: number;
  status?: boolean;
}
