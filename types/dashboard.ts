export interface DashboardOverview {
  statistics: {
    posts: number;
    published_posts: number;
    draft_posts: number;

    categories: number;
    tags: number;

    products: number;
    featured_products: number;

    brands: number;
    affiliate_networks: number;

    users: number;
  };

  recent_posts: import("./post").Post[];
  recent_products: import("./product").AffiliateProduct[];
}

export interface PopularPost {
  id: number;
  title: string;
  slug: string;
  views: number;
}

export interface DashboardAnalytics {
  overview: DashboardOverview;

  popular_posts: PopularPost[];
}

