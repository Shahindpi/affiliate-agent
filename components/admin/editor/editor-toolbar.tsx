"use client";

import type { Editor } from "@tiptap/react";

import {
  Bold,
  Italic,
  Underline,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Undo2,
  Redo2,
  ImageIcon,
  Link2,
} from "lucide-react";

import { Button } from "@/components/ui/button";

interface Props {
  editor: Editor | null;
  onInsertImage: () => void;
  onEditImageAlt: () => void;
}

const ToolbarButton = ({
    active = false,
    onClick,
    children,
    label,
  }: {
    active?: boolean;
    onClick: () => void;
    children: React.ReactNode;
    label: string;
  }) => (
    <Button
        type="button"
        size="sm"
        variant={active ? "default" : "outline"}
        className="cursor-pointer"
        onClick={onClick}
        aria-label={label}
        title={label}
        >
        {children}
    </Button>
  );


export default function EditorToolbar({
  editor,
  onInsertImage,
  onEditImageAlt,
}: Props) {
  if (!editor) return null;


  return (
    <div className="flex flex-wrap gap-2 border-b bg-muted/40 p-3">
      <ToolbarButton
        label="Bold"
        active={editor.isActive("bold")}
        onClick={() =>
          editor.chain().focus().toggleBold().run()
        }
      >
        <Bold className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Italic"
        active={editor.isActive("italic")}
        onClick={() =>
          editor.chain().focus().toggleItalic().run()
        }
      >
        <Italic className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Underline"
        active={editor.isActive("underline")}
        onClick={() =>
          editor.chain().focus().toggleUnderline().run()
        }
      >
        <Underline className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Heading 1"
        active={editor.isActive("heading", { level: 1 })}
        onClick={() =>
          editor
            .chain()
            .focus()
            .toggleHeading({ level: 1 })
            .run()
        }
      >
        <Heading1 className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Heading 2"
        active={editor.isActive("heading", { level: 2 })}
        onClick={() =>
          editor
            .chain()
            .focus()
            .toggleHeading({ level: 2 })
            .run()
        }
      >
        <Heading2 className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Heading 3"
        active={editor.isActive("heading", { level: 3 })}
        onClick={() =>
          editor
            .chain()
            .focus()
            .toggleHeading({ level: 3 })
            .run()
        }
      >
        <Heading3 className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Bullet list"
        active={editor.isActive("bulletList")}
        onClick={() =>
          editor.chain().focus().toggleBulletList().run()
        }
      >
        <List className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Ordered list"
        active={editor.isActive("orderedList")}
        onClick={() =>
          editor.chain().focus().toggleOrderedList().run()
        }
      >
        <ListOrdered className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Insert image"
        onClick={onInsertImage}
      >
        <ImageIcon className="h-4 w-4" />
      </ToolbarButton>

      {editor.isActive("image") && <ToolbarButton label="Edit image alt text" onClick={onEditImageAlt}><span className="text-xs font-semibold">ALT</span></ToolbarButton>}

      <ToolbarButton
        label="Add or edit link"
        active={editor.isActive("link")}
        onClick={() => {
          const previous = editor.getAttributes("link").href as string | undefined;
          const href = window.prompt("Link URL", previous || "https://");
          if (href === null) return;
          if (!href) editor.chain().focus().extendMarkRange("link").unsetLink().run();
          else editor.chain().focus().extendMarkRange("link").setLink({ href }).run();
        }}
      >
        <Link2 className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Block quote"
        active={editor.isActive("blockquote")}
        onClick={() =>
          editor.chain().focus().toggleBlockquote().run()
        }
      >
        <Quote className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Undo"
        onClick={() =>
          editor.chain().focus().undo().run()
        }
      >
        <Undo2 className="h-4 w-4" />
      </ToolbarButton>

      <ToolbarButton
        label="Redo"
        onClick={() =>
          editor.chain().focus().redo().run()
        }
      >
        <Redo2 className="h-4 w-4" />
      </ToolbarButton>
    </div>
  );
}
