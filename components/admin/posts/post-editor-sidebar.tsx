"use client";

import { useState } from "react";
import Image from "next/image";

import { Button } from "@/components/ui/button";

import PostStatusSelect from "./post-status-select";
import MediaPickerModal from "../media/media-picker-modal";

import { imageUrl } from "@/lib/image";
import { MediaItem } from "@/types/media";

interface Props {
  status: "draft" | "published";
  setStatus: (value: "draft" | "published") => void;

  featuredImage?: string | null;
  setFeaturedImage?: (url: string) => void;
}

export default function PostEditorSidebar({
  status,
  setStatus,
  featuredImage,
  setFeaturedImage,
}: Props) {
  const [mediaOpen, setMediaOpen] = useState(false);

  return (
    <>
      <aside className="space-y-6">
        {/* Featured Image */}
        <div className="rounded-2xl border bg-background p-5 space-y-4">
          <div>
            <h3 className="font-semibold">Featured Image</h3>

            <p className="text-sm text-muted-foreground">
              Choose an image for this post.
            </p>
          </div>

          {featuredImage ? (
            <Image
              unoptimized
              width={640}
              height={352}
              src={imageUrl(featuredImage)}
              alt="Featured"
              className="h-44 w-full rounded-xl border object-cover"
            />
          ) : (
            <div className="flex h-44 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
              No featured image selected
            </div>
          )}

          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={() => setMediaOpen(true)}
          >
            {featuredImage ? "Change Featured Image" : "Select Featured Image"}
          </Button>
        </div>

        {/* Publishing */}
        <div className="rounded-2xl border bg-background p-5 space-y-4">
          <div>
            <h3 className="font-semibold">Publishing</h3>

            <p className="text-sm text-muted-foreground">
              Configure post visibility.
            </p>
          </div>

          <PostStatusSelect value={status} onChange={setStatus} />
        </div>
      </aside>

      {/* Media Library */}
      <MediaPickerModal
        open={mediaOpen}
        onClose={() => setMediaOpen(false)}
        onSelect={(image: MediaItem) => {
          setFeaturedImage?.(image.path);
          setMediaOpen(false);
        }}
      />
    </>
  );
}
