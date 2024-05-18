import L from 'leaflet';
import markerIconUrl from './images/marker.png';
import markerDefaultIconUrl from './images/leaflet/marker-icon.png';
// import gpsPointImage from './images/gps_point_2x.png';
// import gpsCompassImage from './images/gps_compass_2x.png';

const dataImageBase64 = 'data:image/png;base64,';
const gpsPointImage = `${dataImageBase64}iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAMAAACdt4HsAAABKVBMVEUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABra2toaGifn5+enp6mpqa3t7e3t7e1tbW2trbNzc3MzMzMzMzLy8vW1tbW1tbV1dXd3d3c3Nze3t7d3d3d3d3e3t7d3d3f39/l5eXm5ubl5eXu7u7w8PDw8PDz8/Pz8/Py8vL09PT09PT+/v79/f3+/v7+/v7////x9//h7v/T5v621v611v6Kvf6Kvf16tP1trf1srP1OnP1NnP0+lP09k/0YgvwXgvwIevwwCV4ZAAAAUXRSTlMAAQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYGRobHB0eHyAhIiMkJSYnKClBQFtaXmpra2yDgoODk5WUnZ2fnp+goKSxsrLIy8zU1dXY2fn5+/zsa7/ZAAAD00lEQVR42qVX23LTMBTU1UmaNAkQrh144v8/iEfKA7QdoOkljnVDOucocpvKZMZr1XQs755dSdQSGwA/YOilgcelL8DtZAGeWvkJ5Sf8V4BodKVGTLhQZliAuNigl9jQ4BoS4Ay5hCKAQBUWqgKc6AJaHn6ie2hopCJAtUW6RLqTALC99+lOPl4QQPPIJYAC8guCBxfHAlgd6FJCAwHOwL5LV2wgAcg02asveCRKFaEb3TTzN+vXm/eb1flizrmUlAlN9eo+4YusoGarswkr2LV/9sZZa51zkOOQQmYddB+LRzTTL29nivWgZ68WLU1tIMpTAeBLJVX0Pl1fNOwIehU8rdO+gugPoJQ68ldfP0j2AuSHr+um0ToNRwI9RS2Mr1L9zSfOKuArYw9rIKAFdXAQFYD/rphWQihmvY80woW+oaXJOUpJNED1dbP+yBBiOp8oKRiLupOJ8FR47vf0f4omU/QNKL28ILPN+RS6stp5Q8HeL7VSsMgY7w0iWZhk/mJe6CgxX3BKMdFKogIJcH4YgiXS5FKxI6ilRMYSHQByBFqDekP1BXsBgjxstEwOyIIoy1Cqz6rw6wrxNYX8p4Mo1WwK7zWKVaBwgU5nuJbIQVLCQVzTiLMqphhiDYMIRIqAfwZm+JKoCwh8RUsR8TyCmFACNgAN94UWJQLQIcQCX+FDAgIVZoJIIJBDTKjGICTcz3IAFKAQih4MQmFMCpDXAX6F9CkCSFNAAA1VvmgoIE9yAAQqOBIkEOJl4DfHqijdJhGKQMDvLgr4Orl0GyCE0HMQYU8XsOXbJHIC7/fkrorSbb3PGQQlCMHfZ3d15Jy3nkggABLJQQudXY1cOtu99zmEoAARboe9YcAAvrLDrzTDQaQA3rm/OEy7usAOxf865ylEL4KzO8rgany3R487dxQhpAj20oLcXagEuIN/4msWFFggAeI7Z65ZVaE8vja0ScgOAiA5sNuAVm+PUpSHYYsGAHkh4SBa013mYo/hWflHEmeXxliHEcrHleV9peuWVK/jkhd6d2/p15vfxhgQKF9n2taCiuVnxDGtg1mKztpHwzL/yhiKgArqsJn13KWSV2xzWPeZVvBj20WBMot5FmKDabC26359D9WF+P226yIfEgCtOICBdOBl+235VrFj2OutiUh0mgESIAmfhaKV+8/TI36bhj/CWY+LoMxCHseys/fbx8BVn33382af6tvDGiobTQIPnJV13T1sH7x1sd/s24erm22b2ECnjerYzfbY7f7JB47ooHbgGH/kGX/oGn/sG3/wHH/0HX/4Hn38/wdqRubmdXYrUgAAAABJRU5ErkJggg==`;
const gpsCompassImage = `${dataImageBase64}iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAMAAAD04JH5AAABd1BMVEUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAhISEAAAAAAAAAAAAeHh4AAAAdHR0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACPj48AAACFhYW3t7exsbGurq57e3sAAACenp6YmJjGxsbDw8NkZGTQ0NCpqanNzc1/f3+7u7va2tq4uLi3t7e2tra1tbXU1NRtbW3Dw8PCwsLCwsLBwcHBwcHV1dXp6enj4+Pv7+/s7Ozy8vLx8fHx8fHs7Oz19fXz8/P39/fy8vL19fX4+Pj6+vr7+/v6+vr8/Pz////+/v7+/v7////9/f3x9//h7v/T5v7F3v6nzf6Zxf6Kvf18tf1trf1OnP1NnP0+lP09k/0ujPwti/wYgvwXgvwIevyWWkiLAAAAa3RSTlMAAQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYGRobHB0eHyEiIyQlJiYnKCorLC0vMDEyMzY3PEJDR0tOT09OVVlaXGFpaGtsdHd2dnd4en+EhYaGh42dqbK0wcbLys/V19bZ3OXv8fL8/P3//pgqqoYAAANNSURBVHja7ZrLaxNRFMa/OXMzmUfSuqlPtFrBYgqlYNGlov//RtFSaTZFd2Kb2tfMfRznxgYnBAq5ZaaI5zeLkLv5fvc780hIINyIaMrtpVMcKxXHdDsOUaySfpplaT9RFG5ACMPvvpekmRfoKUXBChRef9LPitfv378p8rSngsdAgflxr58WT99urK1tvKsNEhUHGlBgvkrSYmP7DmrufHg8yIINKGz+qq5/c5QAWQYkO+t5sAEF5fd8/nMCFXleEGjreeENKMCAgvf/DKAiBdKCgBebRd7vhRhQYP5oHVCrCWqSoQKebhZZP6QDCup/MHoEqCFhivIG694goAMKyM+HL5v5AHmDJ1uDkClQwP6Ho4dAskrAX4MEeLg1COiAAvY/uu8HD8+cwf2Xw+U7oOXuvyrJVl/dm+U38QYPXvk7EkVRSwI+P13ZXZnlLxqsvFlJvUELArMCBrs5kF3l67mXYQbku4MlK6AlPwFsD4A8vwo+xZTTKwO/PthOVNziCHbuAnk2y3eY4mYGWQ7c3WlzBPEakM7nzxukwFrc3giIUqBo5C8YFEBKLTXQpJnfNGj7cczuDNCz/KjRzcxAA2eO2xJgZy+BcwuUdT43cphrgxKw58ClddyOADtnDi3M8cnE539Hg+/eYHJybGAPjXPcUgPWjD8B0AbQ+0docDTWgNEAvo6NbasBdqY8+HJUApMfeycVGlQ/935MgPLoy+fKOF7CQGGZGRjwpz1/nZPKUjRw5fE3x3DGaGMcoy0BGLZ1PihO4mROoLqsrAM7Wx+O27sMrdHlZU1ZaUYD1tWfdW1se5chwGytMVovxHg1v2ysZUZ7AmB2VzBfs96egIc91y2HC9wcERABERABEehQgHn+3X/XAC8sdCvAiyPgLgUYiwLgbkfADg0cdz0CdvMCjrlbAWcrNNDWdSsAZ80FMPtGfGGsQ5cCzM7qC+CCAfavlXXM3Y7AVIcMPTk9nWjwYWU6HgGz1eOPgC1LCxyMte3+JNTlwf4vA5hf+x/L8JMwusnPdqoXE2oVo23XAt6gViAiOLZ1vGN0LIAoovoAwK4+GJ0LIKod5p9E3RNNkX8RCIIgCIIgCIIgCIIgCIIgCIIgCIIgCMK/zW85/6UDAncNTgAAAABJRU5ErkJggg==`;

export const markerDefaultIcon = L.icon({
  iconUrl: markerDefaultIconUrl,
  iconSize: [25, 41],
  iconAnchor: [12, 20],
  popupAnchor: [0, 0],
});

export const addLatLonToUrl = (lat, lon, url) => url.replace('_LAT_', lat).replace('_LON_', lon);

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
