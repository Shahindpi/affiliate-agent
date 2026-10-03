export interface SiteSettings {
  site_name: string;
  site_tagline: string | null;

  logo: string | null;
  favicon: string | null;

  contact?: { email: string | null; phone: string | null; address: string | null };
  social?: Record<string, string | null>;
}
