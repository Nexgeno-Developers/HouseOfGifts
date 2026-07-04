const http = require('http');
const url = 'http://localhost/hog/admin/opsdesk/order?combo_id=1';
http.get(url, res => {
  let data = '';
  res.on('data', chunk => data += chunk);
  res.on('end', () => {
    data.split('\n').forEach(line => {
      if (line.includes('id="opsdesk_order_qty"') || line.includes('name="quantity"') || line.includes('type="number"')) {
        console.log(line);
      }
    });
  });
}).on('error', err => console.error('ERROR', err));
