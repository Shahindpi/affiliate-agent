"use client";
import { routes } from "@/lib/routes";

import { useQuery } from "@tanstack/react-query";

import PageTitle from "@/components/admin/page-title";
import StatCard from "@/components/admin/stat-card";
import PopularPosts from "@/components/admin/popular-posts";

import {
  FileText,
  FolderTree,
  ShoppingBag,
  Users,
  MessageSquare,
  Mail,
  Eye,
} from "lucide-react";

import {
  getDashboardOverview,
  getDashboardPopularPosts,
} from "@/services/dashboard";

export default function DashboardPage() {
  const { data: overview, isLoading } = useQuery({
    queryKey: ["dashboard-overview"],
    queryFn: getDashboardOverview,
  });

  const { data: popularPosts } = useQuery({
    queryKey: ["dashboard-popular-posts"],
    queryFn: getDashboardPopularPosts,
  });

  if (isLoading || !overview) {
    return (
      <main className="space-y-8">
        <PageTitle
          title="Dashboard"
          description="Loading dashboard..."
        />

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
          {Array.from({ length: 8 }).map((_, index) => (
            <div
              key={index}
              className="h-36 rounded-2xl bg-muted animate-pulse"
            />
          ))}
        </div>
      </main>
    );
  }

  return (
    <main className="space-y-8">
      <PageTitle
        title="Dashboard"
        description="Welcome to the Dewdora CMS administration panel."
      />

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <StatCard
          title="Posts"
          value={overview.statistics.posts}
          icon={FileText}
          color="#2563EB"
          href={routes.admin.posts.index}
        />

        <StatCard
          title="Categories"
          value={overview.statistics.categories}
          icon={FolderTree}
          color="#EA580C"
          href={routes.admin.categories}
        />

        <StatCard
          title="Products"
          value={overview.statistics.products}
          icon={ShoppingBag}
          color="#16A34A"
          href={routes.admin.products.index}
        />

        <StatCard
          title="Users"
          value={overview.statistics.users}
          icon={Users}
          color="#7C3AED"
          href={routes.admin.users.index}
        />

        <StatCard
          title="Tags"
          value={overview.statistics.tags}
          icon={MessageSquare}
          color="#DC2626"
          href={routes.admin.tags}
        />

        <StatCard
          title="Brands"
          value={overview.statistics.brands}
          icon={ShoppingBag}
          color="#0891B2"
          href={routes.admin.brands}
        />

        <StatCard
          title="Affiliate Networks"
          value={overview.statistics.affiliate_networks}
          icon={Mail}
          color="#0F766E"
          href={routes.admin.networks}
        />

        <StatCard
          title="Published Posts"
          value={overview.statistics.published_posts}
          icon={Eye}
          color="#64748B"
          href={routes.admin.posts.index}
        />
      </div>

      {popularPosts?.length && (
        <PopularPosts posts={popularPosts} />
      )}
    </main>
  );
}
