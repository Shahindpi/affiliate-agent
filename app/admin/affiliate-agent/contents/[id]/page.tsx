import { notFound } from "next/navigation";
import ReviewDetail from "@/components/admin/affiliate-agent/review-detail";
export default async function Page({ params }: { params: Promise<{ id: string }> }) { const { id } = await params; if (!/^\d+$/.test(id)) notFound(); return <ReviewDetail id={Number(id)} />; }
