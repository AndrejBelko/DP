import pandas as pd
import libgeohash as gh
import geojson
import json
from shapely import geometry
import sys

def geohash_to_polygon(geohash):
    x = gh.bbox(geohash, True)
    x.append(x[0])
    return geometry.Polygon(x)


def shapely_polygon_to_coordinates(polygon):
    return [(lat, lon) for lon, lat in polygon.exterior.coords]


def generate_geojson(geohash_list):
    features = []
    for gh_code in geohash_list:
        polygon = geohash_to_polygon(gh_code)
        coordinates = shapely_polygon_to_coordinates(polygon)
        features.append(geojson.Feature(geometry=geojson.Polygon([coordinates]), properties={"geohash": gh_code}))

    return geojson.FeatureCollection(features=features)


# Load geohashes from CSV
db_name = sys.argv[1]
path = f"/home/data/import/files/db/{db_name}/{db_name}_path.csv"
df = pd.read_csv(path, sep=';')
geohash_list = df['geohash'].unique().tolist()

# Generate and save GeoJSON
geojson_data = generate_geojson(geohash_list)

# Save the GeoJSON file
output_geojson_path = f'/var/www/html/coverage/{db_name}.geojson'
with open(output_geojson_path, 'w') as f:
    geojson.dump(geojson_data, f)

# Save geohash list as JSON
output_json_path = f"/var/www/html/coverage/{db_name}_components.json"
with open(output_json_path, "w") as outfile:
    json.dump(geohash_list, outfile)

print("GeoJSON and geohash list saved.")