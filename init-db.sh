# setenv SEARCH_URL http://123.123.123.123:9200/mapping

curl -XDELETE ${SEARCH_URL}
curl -XPUT ${SEARCH_URL} -d '{"settings": { "analysis":{ "analyzer":{ "default":{"type":"cjk"} } }} }'

curl -XPUT ${SEARCH_URL}/entry/_mapping -d '
{
 "entry" : {
  "date_detection": false,
  "properties" : {
    "title" : {"type" : "string", "store" : false, "index" : "analyzed", "analyzer" : "cjk" },
    "content" : {"type" : "string", "store" : false, "index" : "analyzed", "analyzer" : "cjk" }
  }
 }
}
'

