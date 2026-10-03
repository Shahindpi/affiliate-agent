"use client";
import { useEffect, useState, type FormEvent } from "react";
import api from "@/lib/axios";

type Comment = { id: number; name: string; comment: string; created_at?: string };
export function Comments({ slug }: { slug: string }) {
  const [comments, setComments] = useState<Comment[]>([]);
  const [message, setMessage] = useState("");
  useEffect(() => { api.get(`/public/posts/${encodeURIComponent(slug)}/comments`).then(r => setComments(r.data.data?.comments || [])).catch(() => {}); }, [slug]);
  async function submit(e: FormEvent<HTMLFormElement>) { e.preventDefault(); const form = e.currentTarget; const fields = new FormData(form); try { await api.post(`/public/posts/${encodeURIComponent(slug)}/comments`, Object.fromEntries(fields)); setMessage("Thanks! Your comment is awaiting moderation."); form.reset(); } catch { setMessage("Could not submit your comment. Please check your details."); } }
  return <section className="mt-14 border-t pt-8"><h2 className="text-2xl font-bold">Comments</h2>{comments.map(c => <div key={c.id} className="mt-5 rounded-xl bg-white p-5"><strong>{c.name}</strong><p className="mt-2">{c.comment}</p></div>)}<form onSubmit={submit} className="mt-8 space-y-3"><input name="name" required placeholder="Your name" className="w-full rounded-lg border p-3" /><input name="email" type="email" required placeholder="Email" className="w-full rounded-lg border p-3" /><textarea name="comment" required minLength={5} placeholder="Your comment" className="w-full rounded-lg border p-3" rows={4} /><button className="rounded-lg bg-[#165e46] px-5 py-3 text-white">Submit comment</button></form><p role="status" className="mt-3">{message}</p></section>;
}
export function ContactForm() {
  const [message, setMessage] = useState("");
  async function submit(e: FormEvent<HTMLFormElement>) { e.preventDefault(); const form = e.currentTarget; try { await api.post("/public/contact", Object.fromEntries(new FormData(form))); setMessage("Message sent. Thank you for reaching out!"); form.reset(); } catch { setMessage("Could not send your message. Please try again."); } }
  return <form onSubmit={submit} className="mt-8 max-w-xl space-y-4"><input name="name" required placeholder="Name" className="w-full rounded-lg border p-3" /><input name="email" type="email" required placeholder="Email" className="w-full rounded-lg border p-3" /><input name="subject" required placeholder="Subject" className="w-full rounded-lg border p-3" /><textarea name="message" required minLength={10} placeholder="Message" className="w-full rounded-lg border p-3" rows={6} /><button className="rounded-lg bg-[#165e46] px-6 py-3 font-semibold text-white">Send message</button><p role="status">{message}</p></form>;
}
export function NewsletterForm() {
 const [message, setMessage] = useState("");
 async function submit(e: FormEvent<HTMLFormElement>) { e.preventDefault(); const form = e.currentTarget; try { const response = await api.post("/public/newsletter/subscribe", Object.fromEntries(new FormData(form))); setMessage(response.data.message || "Subscribed!"); form.reset(); } catch { setMessage("Could not subscribe. Please try again."); } }
 return <form onSubmit={submit} className="mt-5 flex max-w-lg flex-wrap gap-3"><input name="email" type="email" required placeholder="Your email address" className="min-w-0 flex-1 rounded-lg border bg-white p-3 text-[#18352d] placeholder:text-[#567069]" /><button className="rounded-lg bg-[#e8a854] px-6 py-3 font-semibold text-[#18352d]">Subscribe</button><p role="status" className="w-full">{message}</p></form>;
}
