/**
 * Given a JSON, return a fatten version of it using dot notation.
 *
 * Example:
 * {
 *    type: 'Feature',
 *    geometry: {
 *      type: 'Point',
 *      coordinates: [
 *        14.25,
 *        40.8333,
 *      ],
 *    },
 *    properties: {
 *      city: 'Naples',
 *      clouds: 0,
 *      country: 'IT',
 *      humidity: 41,
 *      pressure: 1033,
 *      temp: 11.52,
 *      feels_like: 9.8,
 *      wind_deg: 50,
 *      wind_speed: 4.63,
 *   },
 * }
 * Gives:
 * {
 *   "geometry.coordinates.0": 14.25,
 *   "geometry.coordinates.1": 40.8333,
 *   "geometry.type": "Point",
 *   "properties.city": "Naples",
 *   "properties.clouds": 0,
 *   "properties.country": "IT",
 *   "properties.feels_like": 9.8,
 *   "properties.humidity": 41,
 *   "properties.pressure": 1033,
 *   "properties.temp": 11.52,
 *   "properties.wind_deg": 50,
 *   "properties.wind_speed": 4.63,
 *   "type": "Feature"
 * }
 */
export const flattenJsonDataToDotNotation = (jsonData, currentPrefix, result) => {
  currentPrefix = currentPrefix || '';
  result = result || {};

  switch (typeof (jsonData)) {
    case 'object':
      if (Array.isArray(jsonData)) {
        jsonData.forEach((item, i) => {
          const tmpPrefix = currentPrefix.length > 0 ? `${currentPrefix}.${i}` : currentPrefix + i;
          flattenJsonDataToDotNotation(item, tmpPrefix, result);
        });
      } else {
        Object.keys(jsonData).forEach((key) => {
          const tmpPrefix = currentPrefix.length > 0 ? `${currentPrefix}.${key}` : currentPrefix + key;
          flattenJsonDataToDotNotation(jsonData[key], tmpPrefix, result);
        });
      }
      break;
    case 'undefined':
      return null;
    default:
      result[currentPrefix] = jsonData;
  }
  return result;
};

/**
 * Given a structured JSON representing an HTML tree, return the HTMLElement representing it.
 * If you pass a flattenJsonDataDottedNotation object, the structured JSON could use the key from it
 * as `content` (content = '%key%') and get the value.
 *
 * Example:
 * {
 *   "el": "div",
 *   "style": "background: red;",
 *   "class": "small",
 *   "children": [
 *     {
 *       "el": "ul",
 *       "children": [
 *         {
 *           "el": "li",
 *           "content": "%geometry.coordinates.0%"
 *         },
 *         {
 *           "el": "li",
 *           "content": "%geometry.coordinates.1%"
 *         }
 *       ]
 *     }
 *   ]
 * }
 * Gives an HTMLElement like this:
 * <div style="background: red;" class="small">
 *   <ul>
 *     <li>14.25</li>
 *     <li>40.8333</li>
 *   </ul>
 * </div>
 *
 * @TODO this would need a white/blacklist mechanism
 */
export const jsonToHtml = (json, flattenJsonDataDottedNotation) => {
  const el = document.createElement(json.el);
  for (const attributeKey in json) {
    if (attributeKey !== 'el' && attributeKey !== 'children' && attributeKey !== 'content') {
      el.setAttribute(attributeKey, json[attributeKey]);
    }
  }

  if (json.content) {
    let { content } = json;
    for (const k in flattenJsonDataDottedNotation) {
      const v = flattenJsonDataDottedNotation[k];
      content = content.replace(`%${k}%`, v);
    }
    el.textContent = content;
  } else if (json.children) {
    for (const child of json.children) {
      const childEl = jsonToHtml(child, flattenJsonDataDottedNotation);
      el.appendChild(childEl);
    }
  }

  return el;
};
