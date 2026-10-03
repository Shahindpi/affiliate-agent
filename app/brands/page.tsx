import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/brands", "Brands", "Explore brands and their recommended products.");
import { ListPage } from "@/components/public/list-page";
export default async function Page({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const { page } = await searchParams;
  return <ListPage kind="brands" title="Brands" query={{ page: page || "1" }} />;
}
