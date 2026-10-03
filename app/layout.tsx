import "./globals.css";
import type { Metadata } from "next";
import { siteOrigin, defaultImage } from "@/lib/seo";
export const metadata: Metadata = { metadataBase: new URL(siteOrigin), applicationName: "Dewdora", title: { default: "Dewdora | Product discovery and buying guides", template: "%s | Dewdora" }, description: "Discover useful products, independent reviews and practical buying guides from Dewdora.", openGraph: { title: "Dewdora", description: "Discover useful products, reviews and buying guides.", siteName: "Dewdora", type: "website", images: [defaultImage] }, twitter: { card: "summary_large_image", images: [defaultImage] } };

import { ThemeProvider } from "@/providers/theme-provider";
import { QueryProvider } from "@/providers/query-provider";
import { AuthProvider } from "@/providers/auth-provider";
import { ReduxProvider } from "@/providers/redux-provider";
import { Inter } from "next/font/google";

import { Toaster } from "sonner";
import { GoogleAnalytics } from "@/components/analytics/google-analytics";

const inter = Inter({
  subsets: ["latin"],
  display: "swap",
});

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={inter.className}>
        <ThemeProvider>
          <ReduxProvider>
            <QueryProvider>
              <AuthProvider>
                {children}
                <Toaster richColors position="top-right" />
              </AuthProvider>
            </QueryProvider>
          </ReduxProvider>
        </ThemeProvider>
      </body>
      <GoogleAnalytics />
    </html>
  );
}
