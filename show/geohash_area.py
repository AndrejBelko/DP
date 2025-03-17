import pandas as pd
import libgeohash as gh
import geojson
import json
from shapely import geometry


def geohash_to_polygon(geohash):
    """Convert a geohash to a Shapely polygon."""
    x = gh.bbox(geohash, True)
    x.append(x[0])  # Close the polygon
    return geometry.Polygon(x)


def shapely_polygon_to_coordinates(polygon):
    """Convert a Shapely polygon to GeoJSON coordinate format."""
    return [(lat, lon) for lon, lat in polygon.exterior.coords]  # Flip order for GeoJSON


def generate_geojson(geohash_list):
    """Generate GeoJSON for individual geohash polygons."""
    features = []
    for gh_code in geohash_list:
        polygon = geohash_to_polygon(gh_code)
        coordinates = shapely_polygon_to_coordinates(polygon)
        features.append(geojson.Feature(geometry=geojson.Polygon([coordinates]), properties={"geohash": gh_code}))

    return geojson.FeatureCollection(features=features)


# Load geohashes from CSV
db_name = "test"
path = f"../data/import/files/db/{db_name}/{db_name}_path.csv"
df = pd.read_csv(path, sep=';')
geohash_list = df['geohash'].unique().tolist()

# Generate and save GeoJSON
geojson_data = generate_geojson(geohash_list)

# Save the GeoJSON file
output_geojson_path = f'coverage/{db_name}.geojson'
with open(output_geojson_path, 'w') as f:
    geojson.dump(geojson_data, f)

# Save geohash list as JSON
output_json_path = f"coverage/{db_name}_components.json"
with open(output_json_path, "w") as outfile:
    json.dump(geohash_list, outfile)

print("GeoJSON and geohash list saved.")
