import Image from "next/image";
import Link from "next/link";
import { imageUrl } from "@/lib/image";
import { routes } from "@/lib/routes";
import type { PublicPost } from "@/lib/public-api";

export function PostCard({ post }: { post: PublicPost }) {
  return <Link href={routes.posts.show(post.slug)} className="group overflow-hidden rounded-2xl border border-[#dce6d9] bg-white transition hover:-translate-y-1 hover:shadow-lg">
    {post.featured_image && <Image width={720} height={384} sizes="(max-width: 768px) 100vw, 33vw" src={imageUrl(post.featured_image)} alt={`${post.title} featured image`} className="h-48 w-full" />}
    <div className="p-6"><p className="text-xs font-bold uppercase tracking-widest text-[#2c9873]">{post.category?.name || "Article"}</p><h3 className="mt-2 text-xl font-bold group-hover:text-[#2c9873]">{post.title}</h3><p className="mt-3 line-clamp-3 text-sm text-[#567069]">{post.excerpt}</p></div>
  </Link>;
}
