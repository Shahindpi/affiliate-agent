import { notFound } from "next/navigation";
import { ResourcePage } from "@/components/admin/resources/resource-page";
import { resourceConfigs } from "@/components/admin/resources/resource-config";
export default async function Page({ params }: { params: Promise<{ resource: string }> }) { const { resource } = await params; if (!(resource in resourceConfigs)) notFound(); return <ResourcePage resource={resource} />; }
