<template>
  <div>
    <div style="display: flex;">
        <input v-model="dbName" type="text" placeholder="DB name" style="width: 50%;"/>
        <input v-model="dbUser" type="text" placeholder="DB user" style="width: 50%;"/>
    </div>
    <div style="display: flex;">
      <input v-model="dbHost" type="text" placeholder="DB host" style="width: 50%;"/>
      <input v-model="dbPass" type="text" placeholder="DB pass" style="width: 50%;"/>
      <button @click="generatePassword">🔄</button>
    </div>
    
    <div class="language-sql vp-adaptive-theme">
      <button @click="copyToClipboard" class="copy" title="Copy Code">Copy</button>
      <span class="lang">sql</span>
      <pre class="shiki shiki-themes github-light github-dark vp-code" tabindex="0">
        <code>{{ generatedSQL }}</code>
      </pre>
    </div>
  
  </div>
</template>

<script>
export default {
  data() {
    return {
      dbName: '',
      dbUser: '',
      dbHost: 'localhost',
      dbPass: '',
    };
  },
  computed: {
    generatedSQL() {
      return `
CREATE DATABASE ${this.dbName};
CREATE USER '${this.dbUser}'@'${this.dbHost}' IDENTIFIED BY '${this.dbPass}';
GRANT ALL PRIVILEGES ON ${this.dbName}.* TO '${this.dbUser}'@'${this.dbHost}';
FLUSH PRIVILEGES;
EXIT;`;
    },
  },
  methods: {
    generatePassword() {
      this.dbPass = Math.random().toString(36).slice(-12);
    },
  },
};
</script>

<style scoped>
 input {
  margin: 5px;
  padding: 5px;
  border: 1px solid #ccc;
  border-radius: 4px;
}
/*button {
  margin: 5px;
  padding: 5px 10px;
  background-color: #0078d4;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
button:hover {
  background-color: #005a9e;
}
pre {
  background-color: #f4f4f4;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
} */
</style>
