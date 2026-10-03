interface Props {
  title: string;
  description?: string;
}

export default function PageTitle({
  title,
  description,
}: Props) {
  return (
    <div className="mb-8">
      <h1 className="text-3xl font-bold">
        {title}
      </h1>

      {description && (
        <p className="text-muted-foreground mt-2">
          {description}
        </p>
      )}
    </div>
  );
}