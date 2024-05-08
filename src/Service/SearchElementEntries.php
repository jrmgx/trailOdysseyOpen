<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;

class SearchElementEntries
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getEntries(): array
    {
        return $this->cache->get('get-entries-' . sha1(self::ENTRIES), function () {
            $lines = explode(\PHP_EOL, self::ENTRIES);
            $results = [];
            foreach ($lines as $line) {
                if (str_starts_with($line, '//')) {
                    continue;
                }
                $parts = explode(',', $line);
                $results[$parts[0]][] = $parts[1] . '=' . $parts[2];
            }

            return $results;
        });
    }

    private const ENTRIES = <<<ENTRIES
//Abandoned Railway,railway,abandoned
//Aerodrome,aeroway,aerodrome
//Allotment,landuse,allotments
Alpine Hut,tourism,alpine_hut
Ambulance Station,emergency,ambulance_station
//Animal boarding facility,amenity,animal_boarding
//Animal shelter,amenity,animal_shelter
//Apartment Block,building,apartments
//Aquarium,tourism,aquarium
Archaeological Site,historic,archaeological_site
//Art Shop,shop,art
//Arts Center,amenity,arts_centre
//Artwork,tourism,artwork
//Attraction,tourism,attraction
//Bakery,shop,bakery
//Bank,amenity,bank
Bar,amenity,bar
Barbecue,amenity,bbq
//Basin,landuse,basin
//Battlefield,historic,battlefield
//Bay,natural,bay
//Beach Resort,leisure,beach_resort
//Beach,natural,beach
//Beauty Shop,shop,beauty
Bench,amenity,bench
Beverages Shop,shop,beverages
Bicycle Parking,amenity,bicycle_parking
Bicycle Rental,amenity,bicycle_rental
Bicycle Shop,shop,bicycle
Bin,amenity,waste_basket
//Boat Ramp,leisure,slipway
//Boatyard,waterway,boatyard
//Book Shop,shop,books
//Bookcase,amenity,public_bookcase
//Boundary Stone,historic,boundary_stone
//Brewery,craft,brewery
//Bridleway,highway,bridleway
//Brothel,amenity,brothel
//Brownfield Land,landuse,brownfield
//Building Block,building,block
//Building Entrance,building,entrance
//Bunker,building,bunker
Bureau de Change,amenity,bureau_de_change
Bus Station,amenity,bus_station
Bus Stop,highway,bus_stop
Butcher,shop,butcher
//Byway,highway,bywayes
Cafe,amenity,cafe
Camp Site,tourism,camp_site
//Canal,waterway,canal
//Cape,natural,cape
Car Parts,shop,car_parts
Car Rental,amenity,car_rental
Car Repair,shop,car_repair
//Car Sharing,amenity,car_sharing
//Car Shop,shop,car
//Car Wash,amenity,car_wash
Caravan Site,tourism,caravan_site
//Carpenter,craft,carpenter
//Carpet Shop,shop,carpet
Cash machine,amenity,atm
//Casino,amenity,casino
Castle,historic,castle
Cathedral,building,cathedral
Cave Entrance,natural,cave_entrance
Cemetery,landuse,cemetery
//Chalet,tourism,chalet
//Chapel,building,chapel
//Charging station,amenity,charging_station
//Charity Shop,shop,charity
//Chemist,shop,chemist
//Place of worship,amenity,place_of_worship
Church,building,church
//Cinema,amenity,cinema
//City Hall,building,city_hall
//City,place,city
//Civic Building,building,civic
//Cliff,natural,cliff
//Clinic,amenity,clinic
//Clothes Shop,shop,clothes
//Coastline,natural,coastline
//College,amenity,college
//Commercial Area,landuse,commercial
//Commercial Building,building,commercial
Community Center,amenity,community_centre
//Computer Shop,shop,computer
//Confectionery Shop,shop,confectionery
//Conference Center,amenity,conference_centre
//Conservation,landuse,conservation
//Construction,landuse,construction
//Convenience Store,shop,convenience
//Copy Shop,shop,copyshop
//Cosmetics Shop,shop,cosmetics
//Country,place,country
//County,place,county
//Courthouse,amenity,courthouse
//Coworking Space,amenity,coworking_space
//Coworking,office,coworking
//Crematorium,amenity,crematorium
//Cycle Path,highway,cycleway
//Dam,waterway,dam
//Defibrillator,emergency,defibrillator
//Delicatessen,shop,deli
//Dentist,amenity,dentist
//Department Store,shop,department_store
//Derelict Canal,waterway,derelict_canal
//Desert,natural,desert
//Distance Marker,highway,distance_marker
//Distillery,craft,distillery
//Disused Railway,railway,disused
//Ditch,waterway,ditch
//Do-It-Yourself,shop,doityourself
//Dock,waterway,dock
Doctor,amenity,doctors
//Dojo,amenity,dojo
Dormitory,building,dormitory
//Drain,waterway,drain
Drinking Water,amenity,drinking_water
//Driving School,amenity,driving_school
//Dry Cleaning,shop,dry_cleaning
//Electrician,craft,electrician
//Electronics Shop,shop,electronics
//Embassy,amenity,embassy
Emergency Access Point,highway,emergency_access_point
Emergency Phone,emergency,phone
//Estate Agent,shop,estate_agent
//Faculty Building,building,faculty
//Farm Building,building,farm
//Farm Building,building,farm_auxiliary
Farm Shop,shop,farm
//Farm,landuse,farm
Farm,place,farm
//Farmland,landuse,farmland
//Farmyard,landuse,farmyard
//Fashion Shop,shop,fashion
//Fast Food,amenity,fast_food
//Ferry Terminal,amenity,ferry_terminal
//Fire Hydrant,emergency,fire_hydrant
//Fire Station,amenity,fire_station
//Fish Shop,shop,seafood
//Fishing Area,leisure,fishing
//Fitness Center,amenity,gym
//Flats,building,flats
//Florist,shop,florist
//Food Shop,shop,food
Food,amenity,restaurant
//Footpath,highway,footway
//Ford,highway,ford
//Forest,landuse,forest
Fountain,amenity,fountain
//Funeral Director,shop,funeral_directors
//Funicular Railway,railway,funicular
//Furniture,shop,furniture
Garage,building,garage
//Garden Center,shop,garden_centre
//Garden,leisure,garden
Gas Station,amenity,fuel
//Gate,highway,gate
General Store,shop,general
//Gift Shop,shop,gift
//Glacier,natural,glacier
//Golf Course,leisure,golf_course
//Grass,landuse,grass
//Grave Yard,amenity,grave_yard
//Greenfield Land,landuse,greenfield
//Greengrocer,shop,greengrocer
//Greenhouse,building,greenhouse
//Grit bin,amenity,grit_bin
Guest House,tourism,guest_house
//Guided Bus Lane,highway,bus_guideway
//Gym,amenity,gym
//Hackerspace,leisure,hackerspace
//Hairdresser,shop,hairdresser
//Hall,building,hall
//Hamlet,place,hamlet
//Hardware Store,shop,hardware
//Hi-Fi,shop,hifi
//Highway under Construction,highway,construction
Historic Building,historic,building
Hospital Building,building,hospital
Hospital,amenity,hospital
Hostel,tourism,hostel
Hotel,tourism,hotel
//House,building,house
//Houses,place,houses
//Hunting Stand,amenity,hunting_stand
//Ice Cream,amenity,ice_cream
//Ice Rink,leisure,ice_rink
//Industrial Area,landuse,industrial
//Industrial Building,building,industrial
//Information,tourism,information
//Insurance,office,insurance
//Island,place,island
//Islet,place,islet
//Jewelry Shop,shop,jewelry
//Karaoke,amenity,karaoke_box
//Key Cutter,craft,key_cutter
//Kindergarten,amenity,kindergarten
//Kiosk Shop,shop,kiosk
//Kneipp Facility,amenity,kneipp_water_cure
//Landfill,landuse,landfill
Laundry,shop,laundry
//Level Crossing,railway,level_crossing
//Library,amenity,library
//Light Rail,railway,light_rail
//Lighthouse,man_made,lighthouse
//Living Street,highway,living_street
//Locality,place,locality
//Mall,shop,mall
//Manor,historic,manor
//Marina,leisure,marina
//Marketplace,amenity,marketplace
//Marsh,natural,marsh
//Martial Arts,amenity,dojo
//Massage Shop,shop,massage
//Maypole,man_made,maypole
//Meadow,landuse,meadow
Memorial,historic,memorial
//Military Area,landuse,military
//Mine,historic,mine
//Miniature Golf,leisure,miniature_golf
//Mobile Phone Shop,shop,mobile_phone
//Monorail,railway,monorail
Monument,historic,monument
Mosque,building,mosque
Motel,tourism,motel
Motorcycle parking,amenity,motorcycle_parking
Motorcycle Shop,shop,motorcycle
//Motorway Junction,highway,motorway_junction
//Motorway Services,highway,services
//Motorway,highway,motorway
//Municipality,place,municipality
//Mural,artwork_type,mural
Museum,tourism,museum
//Music Shop,shop,music
//Narrow Gauge Railway,railway,narrow_gauge
//Night Club,amenity,nightclub
//Nursery School,amenity,kindergarten
//Nursery,amenity,kindergarten
//Nursing Home,amenity,nursing_home
//Nursing Home,social_facility,nursing_home
//Office Building,building,office
//Optician,shop,optician
//Organic Food Shop,shop,organic
//Outdoor Shop,shop,outdoor
Park,leisure,park
Parking,amenity,parking
//Path,highway,path
//Peak,natural,peak
//Pedestrian Way,highway,pedestrian
//Pet Shop,shop,pet
Pharmacy,amenity,pharmacy
//Photo Shop,shop,photo
//Photographer,craft,photographer
Picnic Site,tourism,picnic_site
//Piste,landuse,piste
//Planetarium,amenity,planetarium
//Plaque,memorial,plaque
//Platform,highway,platform
Playground,leisure,playground
//Police,amenity,police
//Post Box,amenity,post_box
Post Office,amenity,post_office
//Preserved Railway,railway,preserved
//Prison,amenity,prison
Pub,amenity,pub
Public Telephone,amenity,telephone
//Quarry,landuse,quarry
//Raceway,highway,raceway
//Railway Platform,railway,platform
//Railway Points,railway,switch
//Railway Station,building,train_station
//Railway Station,railway,station
//Railway under Construction,railway,construction
//Railway,landuse,railway
//Rapids,waterway,rapids
//Recreation Ground,leisure,recreation_ground
//Recycling Point,amenity,recycling
//Reservoir,landuse,reservoir
//Residential Area,landuse,residential
//Residential Building,building,residential
Rest Area,highway,rest_area
Restaurant,amenity,restaurant
//Retail Building,building,retail
//Retail,landuse,retail
//Retirement Home,amenity,retirement_home
//Ridge,natural,ridge
//River,waterway,river
//Riverbank,waterway,riverbank
//Roundabout,junction,roundabout
Ruin,historic,ruins
//Running Track,leisure,track
//Salon,shop,salon
//Sauna,amenity,sauna
//School Building,building,school
//School,amenity,school
//Sculpture,artwork_type,sculpture
//Sea,place,sea
//Seafood Shop,shop,seafood
//Sex Shop,shop,erotic
//Shelter,amenity,shelter
//Shoe Shop,shop,shoes
//Shoemaker,craft,shoemaker
//Shop,building,shop
//Shopping Center,shop,shopping_centre
//Slipway,leisure,slipway
//Speed Camera,highway,speed_camera
//Sports Centre,leisure,sports_centre
//Sports Pitch,leisure,pitch
//Sports Shop,shop,sports
//Stadium,building,stadium
//Stadium,leisure,stadium
//Station,building,train_station
//Station,railway,station
//Stationery Shop,shop,stationery
Statue,memorial,statue
//Steps,highway,steps
//Stile,highway,stile
//Stolperstein,memorial,stolperstein
//Store,building,store
//Stream,waterway,stream
//Studio,amenity,studio
//Subway Entrance,railway,subway_entrance
//Subway Station,railway,subway
Supermarket,shop,supermarket
//Swimming Pool,leisure,swimming_pool
//Swinger Club,amenity,swingerclub
Synagogue,building,synagogue
//Tailor,craft,tailor
//Tattoo Studio,shop,tattoo
Taxi,amenity,taxi
//Terrace,building,terrace
//Theatre,amenity,theatre
//Theme Park,tourism,theme_park
//Tobacco Shop,shop,tobacco
Toilet,amenity,toilets
//Tower,building,tower
//Town Hall,amenity,townhall
//Toy Shop,shop,toys
//Track,highway,track
//Trail,highway,trail
Train Stop,railway,halt
Tram Stop,railway,tram_stop
//Tramway,railway,tram
//Travel Agency,shop,travel_agency
//University Building,building,university
//University,amenity,university
//Vending Machine,amenity,vending_machine
//Veterinary Surgery,amenity,veterinary
//Video Shop,shop,video
Viewpoint,tourism,viewpoint
//Village Green,landuse,village_green
//Village,place,village
//Vineyard,landuse,vineyard
//Wadi,waterway,wadi
//War Memorial,memorial,war_memorial
//Water Park,leisure,water_park
Water Point,waterway,water_point
//Water well,man_made,water_well
//Waterfall,waterway,waterfall
//Wayside Cross,historic,wayside_cross
//Wayside Shrine,historic,wayside_shrine
//Weir,waterway,weir
//Windmill,man_made,windmill
//Wine Shop,shop,wine
//Winery,craft,winery
//Wreck,historic,wreck
//Zip Line,aerialway,zip_line
//Zoo,tourism,zoo
ENTRIES;
}
