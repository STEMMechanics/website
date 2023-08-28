import { defineStore } from "pinia";

interface CacheItem {
    url: string;
    data: unknown;
}

export const useCacheStore = defineStore({
    id: "cache",
    state: () => ({
        cache: [] as CacheItem[],
    }),

    actions: {
        // Method to retrieve cached JSON data based on a URL
        getCacheByUrl(url: string) {
            const cachedItem = this.cache.find((item) => item.url === url);
            return cachedItem ? cachedItem.data : null;
        },

        // Method to update the cache with new data and check for modifications
        updateCache(url: string, newData: unknown): boolean {
            const index = this.cache.findIndex((item) => item.url === url);

            if (index !== -1) {
                // If the URL is already in the cache, check for modifications
                const existingData = this.cache[index].data;

                if (JSON.stringify(existingData) === JSON.stringify(newData)) {
                    // Data is not modified, return false
                    return false;
                } else {
                    // Data is modified, update the cache
                    this.cache[index].data = newData;
                    return true;
                }
            } else {
                // If the URL is not in the cache, add it
                this.cache.push({ url, data: newData });
                return true;
            }
        },

        // Method to clear the cache for a specific URL
        clearCacheByUrl(url: string) {
            const index = this.cache.findIndex((item) => item.url === url);
            if (index !== -1) {
                this.cache.splice(index, 1);
            }
        },

        // Method to clear the entire cache
        clearCache() {
            this.cache = [];
        },
    },
});
