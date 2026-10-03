import { Category } from "./category";

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
  logo_path?: string | null;
  website?: string | null;
  description?: string | null;
  status?: boolean;
}

export interface AffiliateNetwork {
  id: number;
  name: string;
  slug: string;
  website?: string | null;
  description?: string | null;
  status?: boolean;
}

export interface AffiliateProduct {
  id: number;

  name: string;
  slug: string;

  short_description: string | null;
  description?: string | null;
  website_url?: string | null;
  affiliate_url?: string;
  price: number | null;
  currency: string | null;
  commission_rate?: number | string | null;
  rating: number | string | null;
  free_trial?: boolean;
  featured?: boolean;
  status?: boolean;
  pros?: string[] | null;
  cons?: string[] | null;
  brand_id?: number | null;
  affiliate_network_id?: number | null;
  category_id?: number | null;

  featured_image: string | null;
  featured_image_path?: string | null;

  brand?: Brand;
  affiliate_network?: AffiliateNetwork;
  category?: Category;
}
