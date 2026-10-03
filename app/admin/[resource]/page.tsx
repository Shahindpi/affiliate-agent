import { notFound } from "next/navigation";
import { ResourcePage, resources } from "@/components/admin/resources/resource-page";
export default async function Page({ params }: { params: Promise<{ resource: string }> }) { const { resource } = await params; if (!resources[resource]) notFound(); return <ResourcePage resource={resource} />; }
