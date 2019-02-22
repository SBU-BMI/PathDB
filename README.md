## QuIP - PathDB

## Building:

git clone https://github.com/SBU-BMI/PathDB.git

cd PathDB

docker build -t quip_pathdb:1.0 .

## Running:
docker run --name quip_pathdb --net=quip_nw --restart unless-stopped -itd -p 80:80 quip_pathdb:1.0

## Using the REST API

Examples:

this will return metadata on a sample uploaded image in prototype.  http auth will work

1) to use JWT on REST:

  a) get JWT at: https://vinculum.bmi.stonybrookmedicine.edu/jwt/token
	b) Construct HTTP GET request to https://vinculum.bmi.stonybrookmedicine.edu/node/6?_format=json
	c) add Authorization Bearer <insert JWT from (b) header to request
  d) send

Results:

failed JWT and Http auth yields 403 with: json response

{"message": "Internal Server Error"}

success yields 200 response code and json payload containing the metadata for node.
