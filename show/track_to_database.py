import pandas as pd
import libgeohash as gh
from sqlalchemy import create_engine
import geojson
import csv
import mysql.connector
import sys
import json

# Get the input CSV path and database name from command-line arguments
csvPath = sys.argv[1]
dbName = sys.argv[2]
type = int(sys.argv[3])
title = sys.argv[4]


print("Reading dataset...")
geohash = []

# Try to read the existing path CSV to get the max track ID, if not present create a new one
try:
    dx = pd.read_csv("/home/data/import/files/" + dbName + "_path.csv")
    id = int(dx['track'].max()) + 1
except FileNotFoundError:
    dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched"])
    dx['track'] = dx['track'].astype(int)  # Explicitly cast to int
    dx.to_csv("/home/data/import/files/" + dbName + "_path.csv", index=False, mode="w")
    id = 0

df = pd.read_csv(csvPath)
df = df.fillna('')
df['track'] = id
df['track'] = df['track'].astype(int)  # Explicitly cast to int
# df.rename(columns={'tid': 'track','lng': 'longitude','lat': 'latitude'}, inplace=True)
# path.csv
lat_col = df.columns.get_loc("latitude")
lon_col = df.columns.get_loc("longitude")
track_col = df.columns.get_loc("track")

row = 0
total = 0
for track in df.values:
    geohash.append([gh.encode(track[lat_col], track[lon_col], precision=7), track[track_col], type])
    row += 1
    if row > 1000000:
        dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched"])
        dx['track'] = dx['track'].astype(int)  # Explicitly cast to int
        dx.to_csv("/home/data/import/files/" + dbName + "_path.csv", header=False, index=False, mode="a")
        row = 0
        geohash = []
        total += 1
        print(str(total) + "M lines done...")

dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched"])
dx['track'] = dx['track'].astype(int)  # Explicitly cast to int
dx.to_csv("/home/data/import/files/" + dbName + "_path.csv", header=False, index=False, mode="a")

# --------------------- Track.csv Generation ---------------------

tracks = []

# Try to read the existing track CSV, if not present create a new one
file_path = f"/home/data/import/files/{dbName}_track.csv"
try:
    dx = pd.read_csv(file_path, sep=';')
except FileNotFoundError:
    dx = pd.DataFrame(tracks, columns=['route', 'track', "mapmatched"])
    dx.to_csv(file_path, index=False, mode="w", sep=';', quoting=csv.QUOTE_NONE)

row = 0
total = 0

# Group rows by 'track' and generate GeoJSON
df = pd.read_csv(csvPath)
df['track'] = id
grouped = df.groupby('track')

print(f"Generating geojsons for {len(grouped)} tracks into {dbName}_track.csv...")

for id, values in grouped:
    tracks.append(
        [id, str(geojson.Feature(geometry=geojson.LineString(values[["longitude", "latitude"]].values.tolist()))), type])
    row += 1
    if row > 10000:
        dx = pd.DataFrame(tracks, columns=['route', 'track', 'mapmatched'])
        dx.to_csv("/home/data/import/files/" + dbName + "_track.csv", header=False, index=False, sep=';',
                  quoting=csv.QUOTE_NONE, mode="a")
        row = 0
        tracks = []
        total += 1
        print(str(total * 10) + "k tracks done...")

# Write the final batch of tracks
dx = pd.DataFrame(tracks, columns=['route', 'track', 'mapmatched'])
dx.to_csv(file_path, header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")

print("GeoJSON track generation complete.")

# --------------------- Generating DB Info ---------------------

mapconfig = {"center":{"lat": df["latitude"].median(), "lon": df["longitude"].median()}, "title": title, "dbname": dbName, "attribution": ""}


with open("/var/www/html/center/"+dbName+".json", 'w') as outfile:
    outfile.write(json.dumps(mapconfig))

print(f"DB info written to {dbName}.json.")

print("creating database...")
# Connect to MySQL as 'search' user
mydb = mysql.connector.connect(
    host="localhost",
    user="search",
    password="password"
)
mycursor = mydb.cursor()

# Create the database if it doesn't exist
mycursor.execute(f"CREATE DATABASE IF NOT EXISTS `{dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")
mycursor.close()
mydb.close()

# Reconnect to the newly created database as 'root' with local infile enabled
mydb = mysql.connector.connect(
    host="localhost",
    user="root",
    password="root",
    database=dbName,
    allow_local_infile=True  # Ensure that local_infile is enabled
)
mycursor = mydb.cursor()

# Create 'path' table
mycursor.execute("DROP TABLE IF EXISTS `path`;")
mycursor.execute("""
    CREATE TABLE `path` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `hash` varchar(7) NOT NULL,
        `track` varchar(80) NOT NULL,
        `mapmatched` varchar(80) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `ihash` (`hash`)
    ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;
""")

# Create 'tracks' table
mycursor.execute("DROP TABLE IF EXISTS `tracks`;")
mycursor.execute("""
    CREATE TABLE `tracks` (
        `route` varchar(250) NOT NULL,
        `track` mediumtext NOT NULL,
        `mapmatched` varchar(80) NOT NULL,
        PRIMARY KEY (`route`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
""")

# Enable local infile for the session
mycursor.execute("SET GLOBAL local_infile = 1;")

# Import 'path.csv'
print("Importing tracks ...")
try:
    mycursor.execute(f"""
        LOAD DATA LOCAL INFILE '/home/data/import/files/{dbName}_path.csv'
        INTO TABLE `path`
        FIELDS TERMINATED BY ',' LINES TERMINATED BY '\n' IGNORE 1 LINES (`hash`, `track`, `mapmatched`);
    """)
except mysql.connector.Error as err:
    print(f"Error loading path data: {err}")

# Import 'track.csv'
print("Importing geojsons ...")
try:
    mycursor.execute(f"""
        LOAD DATA LOCAL INFILE '/home/data/import/files/{dbName}_track.csv'
        INTO TABLE `tracks`
        FIELDS TERMINATED BY ';' LINES TERMINATED BY '\n' IGNORE 1 LINES (`route`, `track`, `mapmatched`);
    """)
except mysql.connector.Error as err:
    print(f"Error loading track data: {err}")

# Commit and close
mydb.commit()
mycursor.close()
mydb.close()

