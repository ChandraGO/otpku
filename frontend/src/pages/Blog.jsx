import React from "react";
import { useParams } from "react-router-dom";
import { BlogFeed } from "@/components/BlogFeed";

export default function Blog() {
  const { slug = "" } = useParams();
  return <div className="mx-auto max-w-5xl px-5 py-10 sm:px-6"><BlogFeed initialSlug={slug} /></div>;
}
