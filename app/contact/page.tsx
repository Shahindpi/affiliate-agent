import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/contact", "Contact Dewdora", "Get in touch with Dewdora.");
import { SiteShell } from "@/components/public/site-shell";
import { ContactForm } from "@/components/public/forms";
export default function Page() { return <SiteShell><h1 className="text-4xl font-black">Get in touch</h1><p className="mt-4 text-[#567069]">Have a question or suggestion? Send us a message.</p><ContactForm /></SiteShell>; }
