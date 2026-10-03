import Link from "next/link";
import { publicBannerRoute } from "@/lib/routes";
import Image from "next/image";
export type HeroBanner = { id: number; heading: string; description?: string | null; background_image?: string | null; cta_text?: string | null; cta_url?: string | null };
export function HeroBannerSection({ banner }: { banner?: HeroBanner }) {
  if (!banner) return null;
  const internalCta = banner.cta_url ? publicBannerRoute(banner.cta_url) : null;
  return <section className="relative mt-10 min-h-80 overflow-hidden rounded-3xl bg-[#165e46] text-white">
    {banner.background_image && <Image unoptimized fill priority={false} sizes="(max-width: 768px) 100vw, 1152px" src={banner.background_image} alt="" className="object-cover" />}
    <div className="absolute inset-0 bg-gradient-to-r from-[#092e24]/95 via-[#092e24]/70 to-[#092e24]/20" />
    <div className="relative flex min-h-80 max-w-2xl flex-col justify-center px-8 py-14 md:px-16"><h2 className="text-4xl font-black md:text-5xl">{banner.heading}</h2>{banner.description && <p className="mt-5 text-lg text-white/90">{banner.description}</p>}{banner.cta_text && banner.cta_url && (internalCta ? <Link href={internalCta} className="mt-7 w-fit rounded-lg bg-white px-6 py-3 font-bold text-[#165e46]">{banner.cta_text}</Link> : /^https?:\/\//i.test(banner.cta_url) ? <a href={banner.cta_url} className="mt-7 w-fit rounded-lg bg-white px-6 py-3 font-bold text-[#165e46]">{banner.cta_text}</a> : null)}</div>
  </section>;
}
