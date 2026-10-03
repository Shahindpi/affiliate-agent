import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/posts", "Reviews and buying guides", "Read product reviews, buying guides and practical articles.");
import { ListPage } from "@/components/public/list-page";
export default async function Page({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const { page } = await searchParams;
  return <ListPage kind="posts" title="Posts" query={{ page: page || "1" }} />;
}
