export interface SEOTags {
    title: string;
    description: string;
    keywords: string[];
    robots: {
        index: boolean;
        follow: boolean;
    };
    url: string;
    image: string;
}

export const updateSEOTags = (tags: SEOTags): void => {
    const updateTag = (
        tag: string,
        queryAttribName: string,
        queryAttribValue: string,
        updateAttribName: string,
        updateAttribValue: string
    ) => {
        const existingTag = document.querySelector(
            `${tag}[${queryAttribName}="${queryAttribValue}"]`
        );
        if (existingTag) {
            existingTag.setAttribute(updateAttribName, updateAttribValue);
        } else {
            const metaTag = document.createElement(tag);
            metaTag.setAttribute(queryAttribName, queryAttribValue);
            metaTag.setAttribute(updateAttribName, updateAttribValue);
            document.head.appendChild(metaTag);
        }
    };

    const robotsIndexValue = tags.robots.index ? "index" : "noindex";
    const robotsFollowValue = tags.robots.follow ? "follow" : "nofollow";
    const robotsValue = `${robotsIndexValue}, ${robotsFollowValue}`;

    document.title = `STEMMechanics | ${tags.title}`;
    updateTag("meta", "name", "description", "content", tags.description);
    updateTag("meta", "name", "keywords", "content", tags.keywords.join(", "));
    updateTag("meta", "name", "robots", "content", robotsValue);
    updateTag("link", "rel", "canonical", "href", tags.url);
    updateTag("meta", "property", "og:title", "content", tags.title);
    updateTag(
        "meta",
        "property",
        "og:description",
        "content",
        tags.description
    );
    updateTag("meta", "property", "og:image", "content", tags.image);
    updateTag("meta", "property", "og:url", "content", tags.url);
};
