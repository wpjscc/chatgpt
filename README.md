## install


```
composer create-project wpjscc/chatgpt chatgpt --dev-master
```

## run 

```
cd chatgpt

php app.php 8080 token
```

## visit

http://127.0.0.1:8080



## docker

```
docker run -p 8080:8080 --rm -it wpjscc/chatgpt php app.php 8080 token
```

```
docker build -t wpjscc/chatgpt . -f Dockerfile
docker push wpjscc/chatgpt
```
