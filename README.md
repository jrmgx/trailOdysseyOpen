# Trail Odyssey

Trail Odyssey is a website that allows you to plan hikes from A to Z. 
Including: 
 - creation of the path 
 - planning the hike 
 - preparing your bag
 - live use during the hike
 - and finally share with your friends

[Read more on my blog](https://jerome.gangneux.net/2024/04/15/trail-odyssey/)

## Concepts

### Stage and Routing relation

```
-----------
| Stage A |
-----------
  !__ Routing AB __
                  !
            -----------
            | Stage B |
            -----------
```

Stage have two dates:
- arriving at (the date and time where you plan to arrive at this destination)
- leaving at (the date and time where you plan to leave from this destination)

Stage have 0, 1 or 2 routing(s):
- routing in
- routing out

Routing have two stages:
- start stage
- finish stage

So in this schema:
- Stage A has one routing out: Routing AB
- Routing AB has two stages; start stage: Stage A and finish stage: Stage B
- Stage B has one routing in: Routing AB

### Other concepts

`Mappable` is anything that can be shown on the map

**TODO**

 - Explain: the use of turbo frames templates
 - Explain: Load JS on DOMContentLoaded and re-used when Turbo frame is re-rendered via
`{{ stimulus_js_load_start('diaryController', first_load is defined and first_load) }}`


## Installation

### Prerequisite 

 - Adds `127.0.0.1 trailodyssey.test` into your `/etc/hosts` file
 - Install [castor](https://castor.jolicode.com/)

### First time install

 - `castor infra:build` There nothing to build for now but still
 - `castor infra:up`
 - `castor infra:install`
 - `castor dev:load-fixture` For dev

Head up to https://trailodyssey.test and you should have a running instance!

### Day to day commands

 - `castor up` to start the project
 - `castor down` to stop the project

See `castor` for other commands

## Resources

- [symfony.com/ux-turbo](https://symfony.com/bundles/ux-turbo/current/index.html#accelerating-navigation-with-turbo-drive)
- [stimulus](https://stimulus.hotwired.dev)
- [graphp/graph](https://github.com/graphp/graph)
- [graphp/algorithms](https://github.com/graphp/algorithms)
- [sibyx/phpGPX](https://sibyx.github.io/phpGPX/)
- [leafletjs.com/reference](https://leafletjs.com/reference.html)
- [mpetazzoni/leaflet-gpx](https://github.com/mpetazzoni/leaflet-gpx)
- [overpass api](https://overpass-api.de)
- [overpass turbo](https://overpass-turbo.eu)
- [wiki.openstreetmap.org/Nominatim/Special_Phrases](https://wiki.openstreetmap.org/wiki/Nominatim/Special_Phrases)
- [wiki.openstreetmap.org/Overpass_API](https://wiki.openstreetmap.org/wiki/Overpass_API)
- [wiki.openstreetmap.org/Tag:tourism:camp_site](https://wiki.openstreetmap.org/wiki/Tag:tourism%3Dcamp_site)
- [thunderforest maps outdoors](https://www.thunderforest.com/maps/outdoors/)
- [leaflet routing machine](https://github.com/perliedman/leaflet-routing-machine)
- [apexcharts.com/docs/](https://apexcharts.com/docs/options/chart/events/)
- [turfjs.org/docs/](https://turfjs.org/docs/)
- [Leaflet.GeometryUtil](https://makinacorpus.github.io/Leaflet.GeometryUtil/global.html#length)
- [getbootstrap.com](https://getbootstrap.com/docs/5.3/layout/grid/)
- [icons.getbootstrap.com](https://icons.getbootstrap.com/)
- [Offline](https://github.com/allartk/leaflet.offline)

## Licence

Code, and assets are under Apache License 2.0

## Contributing

Trail Odyssey aims to be an Open Source, community-driven project. Join us by contributing code or documentation.
