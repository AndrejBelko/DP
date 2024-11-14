from geopy.distance import great_circle
from geopy.point import Point
import math, numpy as np
import json
import sys

def getARequest():
 return "[[116.5862274169922,40.054949943999496],[116.58279418945314,40.051665005850715],[116.57558441162111,40.04391192408113],[116.57094955444337,40.038917946926716],[116.56562805175783,40.03444934152963],[116.55103683471681,40.02827166956048],[116.52168273925783,40.01841252357908],[116.51327133178712,40.01486288226098],[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051]]"

def getBRequest():
 return "[[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051],[116.36821746826173,39.98619605209568],[116.32890701293947,39.984749241710226],[116.29440307617189,39.98343393295324],[116.29302978515626,39.989221102071994],[116.29817962646486,39.98935262294526],[116.29817962646486,39.99474476071587],[116.29817962646486,39.996454374049726],[116.30118370056154,39.99625711315695],[116.3035011291504,39.99836119997057]]"


def nwa_similarity(a, b, match, mismatch, distance):
    dist = int(great_circle((a[0], a[1]), (b[0], b[1])).meters)
    if dist < distance:
        return match
    return mismatch


def needleman_wunsh(sequence1, sequence2, match, mismatch, gap, distance):
    l1 = len(sequence1) + 1
    l2 = len(sequence2) + 1

    f = np.zeros((l1, l2), dtype=int)
    f[:, 0] = np.arange(l1) * gap
    f[0, :] = np.arange(l2) * gap
    for i in range(1, l1):
        for j in range(1, l2):
            mx = f[i - 1, j - 1] + nwa_similarity(sequence1[i - 1], sequence2[j - 1], match, mismatch, distance)
            dx = f[i - 1, j] + gap
            ix = f[i, j - 1] + gap
            f[i, j] = max(mx, dx, ix)

    i = l1 - 1
    j = l2 - 1
    match_count = 0
    mismatch_count = 0
    max_subseq_mismatches = 0
    a = []
    b = []
    while i > 0 or j > 0:
        sim = mismatch
        if i > 0 and j > 0: sim = nwa_similarity(sequence1[i - 1], sequence2[j - 1], match, mismatch, distance)

        if i > 0 and j > 0 and f[i][j] == f[i - 1][j - 1] + sim:
            if sim == match:
                a.append("+")
                b.append("+")
                sequence1[i - 1].append("+")
                sequence2[j - 1].append("+")
                max_subseq_mismatches = max(max_subseq_mismatches, mismatch_count)
                mismatch_count = 0
                match_count += 1
            else:
                a.append("x")
                b.append("x")
                sequence1[i - 1].append("x")
                sequence2[j - 1].append("x")
                mismatch_count += 1
            i -= 1
            j -= 1
        elif i > 0 and f[i][j] == f[i - 1][j] + gap:
            a.append("o")
            b.append("_")
            sequence1[i - 1].append("_")
            mismatch_count += 1
            i -= 1
        else:
            a.append("_")
            b.append("o")
            sequence2[j - 1].append("_")
            mismatch_count += 1
            j -= 1

    sim_alpha = 0
    if match_count > 0:
        sim_alpha = match_count / max(len(sequence1), len(sequence2))

    max_subseq_mismatches = max(max_subseq_mismatches, mismatch_count)

    return {"similarity": sim_alpha, "matches": match_count, "max_subseq_mismatches": max_subseq_mismatches,
            "seq1": sequence1, "seq2": sequence2, "alig1": a, "alig2": b}


def get_bearing(lat1, lon1, lat2, lon2):
    x = math.cos(math.radians(lat2)) * math.sin(math.radians(lon2 - lon1))
    y = math.cos(math.radians(lat1)) * math.sin(math.radians(lat2)) \
        - math.sin(math.radians(lat1)) * math.cos(math.radians(lat2)) \
        * math.cos(math.radians(lon2 - lon1))
    brng = np.rad2deg(math.atan2(x, y))
    if brng < 0:
        brng += 360
    return brng


def interpolate_path(locations, distance):
    lat_pos = 0
    lon_pos = 1

    i = 0
    result = []
    while i < len(locations):
        tmpla = locations[i][1] # match input data
        tmplo = locations[i][0] # match input data

        if tmpla < -90 or tmpla > 90 or tmplo < -180 or tmplo > 180:
            i += 1
            continue

        if i == 0:
            result.append([tmpla, tmplo,distance])
            i += 1
            continue

        p1 = result[-1]
        p2 = [tmpla, tmplo]
        d = int(great_circle((p1[lat_pos], p1[lon_pos]), (p2[lat_pos], p2[lon_pos])).meters)

        if d > distance:
            brng = get_bearing(p2[lat_pos], p2[lon_pos], p1[lat_pos], p1[lon_pos])
            xy = d - distance
            while xy >= 0:
                moved = great_circle().destination(Point(p2[lat_pos], p2[lon_pos]), brng, xy / 1000.0)
                result.append([moved.latitude, moved.longitude,distance])
                xy -= distance
        else:
            i += 1
    return result


def measureIt(params):

    pattern_in = json.loads(params[1])   #[]
    search_in = json.loads(params[2])    #[]
    nwa_match = int(params[3])              #1
    nwa_mismatch = int(params[4])             #-1
    nwa_gap = int(params[5])                  #0
    alpha = float(params[6])/100                    #75
    beta = int(params[7])                     #3
    distance = int(params[8])                 #100

    pattern = interpolate_path(pattern_in,distance)
    search = interpolate_path(search_in,distance)

    r = needleman_wunsh(pattern, search, nwa_match, nwa_mismatch, nwa_gap, distance)
    similar = (r["similarity"] >= alpha and r["max_subseq_mismatches"] <= beta)
    r['similarity']*=100
    r["alig1"].reverse()
    r["alig2"].reverse()
    r["config"]={"match": nwa_match, "mismatch": nwa_mismatch, "gap": nwa_gap, "alpha": alpha*100, "beta": beta, "distance": distance}
    return similar, r


if __name__ == '__main__':
    if len(sys.argv)>8:
        similar, r = measureIt(sys.argv)
        r['similar'] = similar
        print(json.dumps(r))
    else:
        print("{}")