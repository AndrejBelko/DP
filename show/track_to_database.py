import pandas as pd
import libgeohash as gh
from sqlalchemy import create_engine
import geojson
import csv
import mysql.connector
import sys
import json
from datetime import datetime
import numpy as np

def haversine_vectorized(latitudes, longitudes):
    R = 6371000  # Earth radius in meters
    lat1 = np.radians(latitudes[:-1])
    lat2 = np.radians(latitudes[1:])
    lon1 = np.radians(longitudes[:-1])
    lon2 = np.radians(longitudes[1:])

    d_phi = lat2 - lat1
    d_lambda = lon2 - lon1

    a = np.sin(d_phi / 2.0)**2 + np.cos(lat1) * np.cos(lat2) * np.sin(d_lambda / 2.0)**2
    c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1 - a))
    return np.sum(R * c)

# Get the input CSV path and database name from command-line arguments
csvPath = sys.argv[1]
dbName = sys.argv[2]
type = int(sys.argv[3])
title = sys.argv[4]
timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

print("Reading dataset...")
geohash = []

# Try to read the existing path CSV to get the max track ID, if not present create a new one
try:
    dx = pd.read_csv("/home/data/import/files/" + dbName + "_path.csv")
    id = int(dx['track'].max()) + 1
except FileNotFoundError:
    dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched", "timestamp", "length"])
    dx['track'] = dx['track'].astype(int)  # Explicitly cast to int
    dx.to_csv("/home/data/import/files/" + dbName + "_path.csv", index=False, mode="w")
    id = 0

df = pd.read_csv(csvPath)
df = df.fillna('')
df['track'] = id
df['track'] = df['track'].astype(int)  # Explicitly cast to int
if "lat" in df.columns:
    df.rename(columns={'lat': 'latitude'}, inplace=True)

if "lon" in df.columns:
    df.rename(columns={'lon': 'longitude'}, inplace=True)

if "LATITUDE N/S" in df.columns:
    df.rename(columns={'LATITUDE N/S': 'latitude'}, inplace=True)
    df['latitude'] = df['latitude'].str.slice(0, -1)
    df['latitude'] = df['latitude'].astype(float)

if "LONGITUDE E/W" in df.columns:
    df.rename(columns={'LONGITUDE E/W': 'longitude'}, inplace=True)
    df['longitude'] = df['longitude'].str.slice(0, -1)
    df['longitude'] = df['longitude'].astype(float)
# path.csv

row = 0
total = 0
grouped = df.groupby('track')
for track_id, values in grouped:
    # Extract latitudes and longitudes for the track
    latitudes = values['latitude'].to_numpy()
    longitudes = values['longitude'].to_numpy()

    # Calculate trajectory length
    trajectory_length = haversine_vectorized(latitudes, longitudes)

    # Add rows for each point in the track
    for _, row_data in values.iterrows():
        geohash.append([
            gh.encode(row_data['latitude'], row_data['longitude'], precision=7),  # Generate geohash
            track_id,  # Track ID
            type,  # Mapmatched flag (placeholder)
            timestamp,  # Current timestamp
            trajectory_length  # Total track length
        ])
        row += 1

    # Write to file in batches
    if row > 1000000:
        dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched", "timestamp", "length"])
        dx.to_csv(f"/home/data/import/files/{dbName}_path.csv", header=False, index=False, mode="a")
        row = 0
        geohash = []
        total += 1

dx = pd.DataFrame(geohash, columns=["geohash","track", "mapmatched", "timestamp", "length"])
dx.to_csv("/home/data/import/files/"+dbName+"_path.csv", header=False, index=False, mode="a")


# --------------------- Track.csv Generation ---------------------

tracks = []

# Try to read the existing track CSV, if not present create a new one
file_path = f"/home/data/import/files/{dbName}_track.csv"
try:
    dx = pd.read_csv(file_path, sep=';')
except FileNotFoundError:
    dx = pd.DataFrame(tracks, columns=['route', 'track', "mapmatched","timestamp", "length"])
    dx.to_csv(file_path, index=False, mode="w", sep=';', quoting=csv.QUOTE_NONE)

row = 0
total = 0

# Group rows by 'track' and generate GeoJSON
df = pd.read_csv(csvPath)
df['track'] = id
grouped = df.groupby('track')
if "lat" in df.columns:
    df.rename(columns={'lat': 'latitude'}, inplace=True)
if "lon" in df.columns:
    df.rename(columns={'lon': 'longitude'}, inplace=True)

if "LATITUDE N/S" in df.columns:
    df.rename(columns={'LATITUDE N/S': 'latitude'}, inplace=True)
    df['latitude'] = df['latitude'].str.slice(0, -1)
    df['latitude'] = df['latitude'].astype(float)

if "LONGITUDE E/W" in df.columns:
    df.rename(columns={'LONGITUDE E/W': 'longitude'}, inplace=True)
    df['longitude'] = df['longitude'].str.slice(0, -1)
    df['longitude'] = df['longitude'].astype(float)

print(f"Generating geojsons for {len(grouped)} tracks into {dbName}_track.csv...")
for id, values in grouped:
    trajectory_length = haversine_vectorized(np.array(values['latitude']), np.array(values['longitude']))
    tracks.append([id, str(geojson.Feature(geometry=geojson.LineString(values[["longitude", "latitude"]].values.tolist()))), type, timestamp, trajectory_length])
    row += 1
    if row > 10000:
        dx = pd.DataFrame(tracks, columns=['route', 'track', 'mapmatched', 'timestamp', "length"])
        dx.to_csv("/home/data/import/files/" + dbName + "_track.csv", header=False, index=False, sep=';',
                  quoting=csv.QUOTE_NONE, mode="a")
        row = 0
        tracks = []
        total += 1
        print(str(total * 10) + "k tracks done...")

# Write the final batch of tracks
dx = pd.DataFrame(tracks, columns=['route', 'track', 'mapmatched', 'timestamp', "length"])
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
        `timestamp` TIMESTAMP NOT NULL,
        `length` FLOAT NOT NULL,
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
        `timestamp` TIMESTAMP NOT NULL,
         `length` FLOAT NOT NULL,
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
        FIELDS TERMINATED BY ',' LINES TERMINATED BY '\n' IGNORE 1 LINES (`hash`, `track`, `mapmatched`, `timestamp`, `length`);
    """)
except mysql.connector.Error as err:
    print(f"Error loading path data: {err}")

# Import 'track.csv'
print("Importing geojsons ...")
try:
    mycursor.execute(f"""
        LOAD DATA LOCAL INFILE '/home/data/import/files/{dbName}_track.csv'
        INTO TABLE `tracks`
        FIELDS TERMINATED BY ';' LINES TERMINATED BY '\n' IGNORE 1 LINES (`route`, `track`, `mapmatched`, `timestamp`, `length`);
    """)
except mysql.connector.Error as err:
    print(f"Error loading track data: {err}")

# Commit and close
mydb.commit()
mycursor.close()
mydb.close()

