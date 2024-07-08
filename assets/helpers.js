import L from 'leaflet';
import markerIconUrl from './images/marker.png';
import markerDefaultIconUrl from './images/leaflet/marker-icon.png';
// import gpsPointImage from './images/gps_point_2x.png';
// import gpsCompassImage from './images/gps_compass_2x.png';

const dataImageBase64 = 'data:image/png;base64,';
const gpsPointImage = `${dataImageBase64}iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAMAAACdt4HsAAABKVBMVEUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABra2toaGifn5+enp6mpqa3t7e3t7e1tbW2trbNzc3MzMzMzMzLy8vW1tbW1tbV1dXd3d3c3Nze3t7d3d3d3d3e3t7d3d3f39/l5eXm5ubl5eXu7u7w8PDw8PDz8/Pz8/Py8vL09PT09PT+/v79/f3+/v7+/v7////x9//h7v/T5v621v611v6Kvf6Kvf16tP1trf1srP1OnP1NnP0+lP09k/0YgvwXgvwIevwwCV4ZAAAAUXRSTlMAAQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYGRobHB0eHyAhIiMkJSYnKClBQFtaXmpra2yDgoODk5WUnZ2fnp+goKSxsrLIy8zU1dXY2fn5+/zsa7/ZAAAD00lEQVR42qVX23LTMBTU1UmaNAkQrh144v8/iEfKA7QdoOkljnVDOucocpvKZMZr1XQs755dSdQSGwA/YOilgcelL8DtZAGeWvkJ5Sf8V4BodKVGTLhQZliAuNigl9jQ4BoS4Ay5hCKAQBUWqgKc6AJaHn6ie2hopCJAtUW6RLqTALC99+lOPl4QQPPIJYAC8guCBxfHAlgd6FJCAwHOwL5LV2wgAcg02asveCRKFaEb3TTzN+vXm/eb1flizrmUlAlN9eo+4YusoGarswkr2LV/9sZZa51zkOOQQmYddB+LRzTTL29nivWgZ68WLU1tIMpTAeBLJVX0Pl1fNOwIehU8rdO+gugPoJQ68ldfP0j2AuSHr+um0ToNRwI9RS2Mr1L9zSfOKuArYw9rIKAFdXAQFYD/rphWQihmvY80woW+oaXJOUpJNED1dbP+yBBiOp8oKRiLupOJ8FR47vf0f4omU/QNKL28ILPN+RS6stp5Q8HeL7VSsMgY7w0iWZhk/mJe6CgxX3BKMdFKogIJcH4YgiXS5FKxI6ilRMYSHQByBFqDekP1BXsBgjxstEwOyIIoy1Cqz6rw6wrxNYX8p4Mo1WwK7zWKVaBwgU5nuJbIQVLCQVzTiLMqphhiDYMIRIqAfwZm+JKoCwh8RUsR8TyCmFACNgAN94UWJQLQIcQCX+FDAgIVZoJIIJBDTKjGICTcz3IAFKAQih4MQmFMCpDXAX6F9CkCSFNAAA1VvmgoIE9yAAQqOBIkEOJl4DfHqijdJhGKQMDvLgr4Orl0GyCE0HMQYU8XsOXbJHIC7/fkrorSbb3PGQQlCMHfZ3d15Jy3nkggABLJQQudXY1cOtu99zmEoAARboe9YcAAvrLDrzTDQaQA3rm/OEy7usAOxf865ylEL4KzO8rgany3R487dxQhpAj20oLcXagEuIN/4msWFFggAeI7Z65ZVaE8vja0ScgOAiA5sNuAVm+PUpSHYYsGAHkh4SBa013mYo/hWflHEmeXxliHEcrHleV9peuWVK/jkhd6d2/p15vfxhgQKF9n2taCiuVnxDGtg1mKztpHwzL/yhiKgArqsJn13KWSV2xzWPeZVvBj20WBMot5FmKDabC26359D9WF+P226yIfEgCtOICBdOBl+235VrFj2OutiUh0mgESIAmfhaKV+8/TI36bhj/CWY+LoMxCHseys/fbx8BVn33382af6tvDGiobTQIPnJV13T1sH7x1sd/s24erm22b2ECnjerYzfbY7f7JB47ooHbgGH/kGX/oGn/sG3/wHH/0HX/4Hn38/wdqRubmdXYrUgAAAABJRU5ErkJggg==`;
const gpsCompassImage = `${dataImageBase64}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`;

export const markerDefaultIcon = L.icon({
  iconUrl: markerDefaultIconUrl,
  iconSize: [25, 41],
  iconAnchor: [12, 20],
  popupAnchor: [0, 0],
});

// eslint-disable-next-line no-unused-vars
export const debugConsole = (message) => {
  // const date = new Date();
  // eslint-disable-next-line max-len
  // const time = `[${date.toLocaleTimeString()}.${date.getMilliseconds().toString().padStart(3, '0')}] `;
  // console.log(`%c${time}${message}`, 'color: blue; font-weight: bold;');
};

export const iconSymbol = (symbol) => L.divIcon({
  html: `<span class="stage-marker"><img alt="" src="${markerIconUrl}${'">%</span>'.replace('%', symbol)}`,
  iconSize: [48, 48],
  iconAnchor: [24, 42],
  popupAnchor: [0, 16],
});

export const iconGpsPoint = L.icon({
  iconUrl: gpsPointImage,
  shadowUrl: '',
  iconSize: [32, 32], // size of the icon
  shadowSize: [0, 0], // size of the shadow
  iconAnchor: [16, 16], // point of the icon which will correspond to marker's location
  shadowAnchor: [0, 0], // the same for the shadow
  popupAnchor: [16, 16], // point from which the popup should open relative to the iconAnchor
});

export const iconGpsCompass = L.icon({
  iconUrl: gpsCompassImage,
  shadowUrl: '',
  iconSize: [64, 64],
  shadowSize: [0, 0],
  iconAnchor: [32, 32],
  shadowAnchor: [0, 0],
  popupAnchor: [32, 32],
});

export const curve = (startPoint, endPoint, pathOptions) => {
  // From: https://gist.github.com/ryancatalani/6091e50bf756088bf9bf5de2017b32e6
  const latlng1 = [startPoint.lat, startPoint.lng];
  const latlng2 = [endPoint.lat, endPoint.lng];
  const offsetX = latlng2[1] - latlng1[1];
  const offsetY = latlng2[0] - latlng1[0];
  const r = Math.sqrt(offsetX ** 2 + offsetY ** 2);
  const theta = Math.atan2(offsetY, offsetX);
  const thetaOffset = (3.14 / 10);
  const r2 = (r / 2) / (Math.cos(thetaOffset));
  const theta2 = theta + thetaOffset;
  const midpointX = (r2 * Math.cos(theta2)) + latlng1[1];
  const midpointY = (r2 * Math.sin(theta2)) + latlng1[0];
  const midpointLatLng = [midpointY, midpointX];
  // noinspection JSUnresolvedFunction
  return L.curve(['M', latlng1, 'Q', midpointLatLng, latlng2], pathOptions);
};

export const removeFromMap = (toRemove, map) => {
  if (!toRemove) {
    return null;
  }
  map.removeLayer(toRemove);
  toRemove.remove();
  return null;
};
