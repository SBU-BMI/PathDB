## QuIP - PathDB

## Building:

git clone https://github.com/SBU-BMI/PathDB.git

cd PathDB

docker build -t quip_pathdb:1.0 .

## Running:
docker run --name quip_pathdb --net=quip_nw --restart unless-stopped -itd -p 80:80 quip_pathdb:1.0
