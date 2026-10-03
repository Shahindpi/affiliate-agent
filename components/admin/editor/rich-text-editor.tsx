"use client";

import { useEffect } from "react";

import { EditorContent, useEditor } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import Placeholder from "@tiptap/extension-placeholder";
import Underline from "@tiptap/extension-underline";
import Link from "@tiptap/extension-link";
import TextAlign from "@tiptap/extension-text-align";

import EditorToolbar from "./editor-toolbar";

import { useState } from "react";
import MediaPickerModal from "@/components/admin/media/media-picker-modal";
import { MediaItem } from "@/types/media";
import Image from "@tiptap/extension-image";

import "./editor-styles.css";

interface Props {
  value: string;
  onChange: (value: string) => void;
  imageAltFallback?: string;
}

export default function RichTextEditor({
  value,
  onChange,
  imageAltFallback = "Editorial image",
}: Props) {
    const [mediaOpen, setMediaOpen] = useState(false);
  const editor = useEditor({
    immediatelyRender: false,

    extensions: [
        StarterKit.configure({ link: false, underline: false }),
        Underline,
        Image,
        Link.configure({
            openOnClick: false,
        }),
        Placeholder.configure({
            placeholder: "Write your article here...",
        }),
        TextAlign.configure({
            types: ["heading", "paragraph"],
        }),
     ],

    content: value,

    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
  });

  /*
  |--------------------------------------------------------------------------
  | Keep editor synced when editing an existing post
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    if (
      editor &&
      value !== editor.getHTML()
    ) {
      editor.commands.setContent(value || "");
    }
  }, [value, editor]);

  if (!editor) return null;

  return (
    <div className="rounded-2xl border bg-background overflow-hidden">
      <EditorToolbar
        editor={editor}
        onInsertImage={() => setMediaOpen(true)}
        onEditImageAlt={() => {
          const current = editor.getAttributes("image").alt as string | undefined;
          const alt = window.prompt("Describe this image for readers using assistive technology", current || imageAltFallback);
          if (alt !== null) editor.chain().focus().updateAttributes("image", { alt }).run();
        }}
       />

      <EditorContent
        editor={editor}
        className="dewdora-editor min-h-[450px]"
      />
      <MediaPickerModal
        open={mediaOpen}
        onClose={() => setMediaOpen(false)}
        onSelect={(image: MediaItem) => {
            editor
            ?.chain()
            .focus()
            .setImage({
                src: image.url,
                alt: image.name || imageAltFallback,
            })
            .run();

            setMediaOpen(false);
        }}
      />
    </div>
  );
}
