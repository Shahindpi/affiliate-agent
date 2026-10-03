export interface Category {
  id: number;
  name: string;
  slug: string;
}

export interface Tag {
  id: number;
  name: string;
  slug: string;
}

export interface Post {
  id: number;
  title: string;
  slug: string;

  excerpt?: string;
  content?: string;
  category_id?: number | null;
  post_type?: "article" | "review" | "comparison" | "tutorial" | "news";
  allow_comments?: boolean;

  featured_image?: string;
  featured_image_path?: string | null;

  status: "draft" | "published";

  views: number;

  published_at: string | null;

  created_at: string;

  category?: Category;

  tags?: Tag[];
}

export interface PaginatedPosts {
  data: Post[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
