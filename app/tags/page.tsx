import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/tags", "Topics", "Explore article topics and guides.");
import { ListPage } from "@/components/public/list-page";
export default async function Page({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const { page } = await searchParams;
  return <ListPage kind="tags" title="Tags" query={{ page: page || "1" }} />;
}
