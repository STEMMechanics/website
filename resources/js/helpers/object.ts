/**
 * Sort a objects properties alphabetically
 *
 * @param {Record<string, unknown>} obj The object to sort
 * @returns {Record<string, unknown>} The object sorted
 */
export const sortProperties = (
    obj: Record<string, unknown>
): Record<string, unknown> => {
    // convert object into array
    const sortable: [string, unknown][] = [];
    for (const key in obj)
        if (Object.prototype.hasOwnProperty.call(obj, key))
            sortable.push([key, obj[key]]); // each item is an array in format [key, value]

    // sort items by value
    sortable.sort(function (a, b) {
        const x = String(a[1]).toLowerCase(),
            y = String(b[1]).toLowerCase();
        return x < y ? -1 : x > y ? 1 : 0;
    });

    const sortedObj: Record<string, unknown> = {};
    sortable.forEach((item) => {
        sortedObj[item[0]] = item[1];
    });

    return sortedObj; // array in format [ [ key1, val1 ], [ key2, val2 ], ... ]
};
