import pandas as pd
import libgeohash as gh
import geojson
import json
from IPython.display import GeoJSON
from IPython.display import IFrame
from shapely import Polygon
from shapely import MultiPolygon
from shapely import geometry
from shapely.ops import cascaded_union
import sys


def geohash_codes_to_geojson(rows):
    f = []
    for code in rows:
        row = gh.bbox(code, True)
        x = [
            row[0][::-1],
            row[1][::-1],
            row[2][::-1],
            row[3][::-1]
        ]
        f.append(geojson.Feature(geometry=geojson.Polygon([x]), properties={"code": code}))

    return geojson.FeatureCollection(features=f)


def geohash_to_polygon(geohash):
    x = gh.bbox(geohash, True)
    x.append(x[0])
    return geometry.Polygon(x)


def shapely_polygon_to_coordinates(polygon):
    x = []
    lon = list(polygon.exterior.coords.xy[0])
    lat = list(polygon.exterior.coords.xy[1])
    for i in range(len(lat)):
        x.append((lat[i], lon[i]))
    return x


def shapely_to_geojson(polygons):
    f = []
    for p in polygons:
        if type(p) == Polygon:
            f.append(geojson.Feature(geometry=geojson.Polygon([shapely_polygon_to_coordinates(p)])))
        else:
            x = []
            for pi in p.geoms:
                x.append(shapely_polygon_to_coordinates(pi))

            f.append(geojson.Feature(geometry=geojson.MultiPolygon([x])))

    return geojson.FeatureCollection(features=f)


def calculate_components(geohash_list):
    R = [{"sn": set(), "sc": set()}]
    y = gh.neighbors(geohash_list[0])
    y = set((y['n'], y['s'], y['e'], y['w'], y['ne'], y['nw'], y['se'], y['sw']))
    R[0]["sc"].add(geohash_list[0])
    R[0]["sn"].update(y)
    R[0]["sn"].add(geohash_list[0])

    for i in range(1, len(geohash_list)):
        Qt = []
        Q = {"sn": set(), "sc": set()}
        found = False
        for j in range(len(R)):
            if set({geohash_list[i]}) & R[j]["sn"]:
                R[j]["sc"].add(geohash_list[i])
                y = gh.neighbors(geohash_list[i])
                y = set((y['n'], y['s'], y['e'], y['w'], y['ne'], y['nw'], y['se'], y['sw']))
                R[j]["sn"].update(y)
                R[j]["sn"].add(geohash_list[i])
                Q["sn"].update(R[j]["sn"])
                Q["sc"].update(R[j]["sc"])
                found = True
            else:
                Qt.append(R[j])
        if found == False:
            y = gh.neighbors(geohash_list[i])
            y = set((y['n'], y['s'], y['e'], y['w'], y['ne'], y['nw'], y['se'], y['sw']))
            R.append({"sn": set({geohash_list[i]}), "sc": set({geohash_list[i]})})
            R[len(R) - 1]["sn"].update(y)
        else:
            Qt.append(Q)
            R = Qt
    return R


def generate_geojson_from_components(R):
    components = [i["sc"] for i in R]
    polygons = []
    for component in components:
        polygons.append(cascaded_union([geohash_to_polygon(i) for i in component]))

    return shapely_to_geojson(polygons)


db_name = sys.argv[1]
print(db_name)
path = f"/home/data/import/files/db/{db_name}/{db_name}_path.csv"
print("finished")
df = pd.read_csv(path)
x = df['geohash'].unique()
x = x.tolist()

R = calculate_components(x)
print(len(R))
geo = generate_geojson_from_components(R)
print("finished")

## cache results for later use
with open(f'/var/www/html/coverage/{db_name}.geojson', 'w') as f:
    geojson.dump(geo, f)
jsonString = json.dumps([list(i["sc"]) for i in R])
with open(f"/var/www/html/coverage/{db_name}_components.json", "w") as outfile:
    outfile.write(jsonString)
print("all saved")

with open(f'/var/www/html/coverage/{db_name}_components.json', 'r') as openfile:
    json_object = json.load(openfile)




# def ukazka():
#     x = ['u0yjkbx', 'u0yjm08', 'u0yjm0c', 'u0yjm0f', 'u0yjm0g', 'u0yjm15']
#     # vstup
#     print(geohash_codes_to_geojson(x))
#
#     R = calculate_components(x)
#     geo = generate_geojson_from_components(R)
#     # vystup
#     print(geo)
#
#     # ukazka 2
#     x = ['u2s4hxj', 'u2s4hxp', 'u2s4hxn', 'u2s4hwv', 'u2s4hwt', 'u2s4hwq', 'u2s4hzh', 'u2s4hzj', 'u2s4hys', 'u2s4hyv',
#          'u2s4hyy', 'u2s4hyw', 'u2s4hyg', 'u2s4hzn', 'u2s4hyt', 'u2s4hyu']
#
#     print(geohash_codes_to_geojson(x))
#     R = calculate_components(x)
#     print(len(R))
#     geo = generate_geojson_from_components(R)
#     print(geo)