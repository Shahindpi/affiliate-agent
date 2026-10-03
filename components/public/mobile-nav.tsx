"use client";

import Link from "next/link";
import { publicNavigation } from "@/lib/routes";
import { usePathname } from "next/navigation";
import { useState } from "react";



export function MobileNav() {
  const pathname = usePathname();
  return <Menu key={pathname} />;
}

function Menu() {
  const [open, setOpen] = useState(false);
  return <div className="ml-auto md:hidden">
    <button type="button" aria-label={open ? "Close navigation" : "Open navigation"} aria-expanded={open} aria-controls="mobile-public-navigation" onClick={() => setOpen(value => !value)} className="grid h-11 w-11 cursor-pointer place-items-center rounded-lg border border-[#dce6d9] text-[#18352d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#165e46]">
      <span aria-hidden="true" className="text-2xl leading-none">{open ? "×" : "☰"}</span>
    </button>
    <nav id="mobile-public-navigation" aria-label="Mobile navigation" inert={!open} className={`absolute inset-x-0 top-full z-50 border-b border-[#dce6d9] bg-white p-4 shadow-xl transition-[opacity,transform,visibility] duration-250 motion-reduce:transition-none ${open ? "visible translate-y-0 opacity-100" : "invisible -translate-y-2 opacity-0"}`}>
      <div className="mx-auto grid max-w-6xl gap-1">{publicNavigation.map(({ label, href }) => <Link key={href} href={href} onClick={() => setOpen(false)} className="rounded-lg px-4 py-3 font-semibold text-[#18352d] hover:bg-[#eef4ec] focus-visible:bg-[#eef4ec]">{label}</Link>)}</div>
    </nav>
  </div>;
}
