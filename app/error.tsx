"use client";

export default function ErrorPage({ reset }: { reset: () => void }) {
  return <main className="mx-auto max-w-xl px-6 py-24">
    <h1 className="text-3xl font-bold">We couldn&apos;t load this page</h1>
    <p className="mt-4 text-muted-foreground">The service is temporarily unavailable. Please try again.</p>
    <button onClick={reset} className="mt-6 rounded-lg bg-primary px-5 py-3 text-primary-foreground">Try again</button>
  </main>;
}
